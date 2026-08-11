<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\HistoricalMetric;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TabularImportReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StageHistoricalDataMigration
{
    public const REQUIRED_HEADERS = ['county_code', 'period', 'metric_code', 'metric_name', 'numeric_value', 'narrative_value', 'unit', 'source_reference'];

    public function __construct(
        private AuditLogger $auditLogger,
        private TabularImportReader $tabularImportReader,
    ) {}

    public function handle(User $actor, UploadedFile $file, string $datasetType, string $sourceName, string $sourceReference, string $periodFrom, string $periodTo): DataMigrationBatch
    {
        $from = CarbonImmutable::parse($periodFrom)->startOfDay();
        $to = CarbonImmutable::parse($periodTo)->startOfDay();
        $parsedRows = $this->reconcileAppliedHistory($this->parse($file, $from, $to), $datasetType);
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded source file is no longer available.']);
        }

        $checksum = hash_file('sha256', $realPath);
        if ($checksum === false) {
            throw ValidationException::withMessages(['file' => 'The source file checksum could not be calculated.']);
        }

        $storedPath = $file->storeAs('data-migrations', Str::uuid().'.'.$this->tabularImportReader->extension($file), 'local');
        if (! is_string($storedPath)) {
            throw ValidationException::withMessages(['file' => 'The source file could not be stored privately.']);
        }

        try {
            return DB::transaction(function () use ($actor, $file, $datasetType, $sourceName, $sourceReference, $from, $to, $parsedRows, $checksum, $storedPath): DataMigrationBatch {
                $invalidRows = collect($parsedRows)->where('validation_status', 'invalid')->count();
                $batch = DataMigrationBatch::create([
                    'reference' => 'MIG-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'dataset_type' => $datasetType,
                    'source_name' => $sourceName,
                    'source_reference' => $sourceReference,
                    'period_from' => $from,
                    'period_to' => $to,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'text/csv',
                    'size_bytes' => $file->getSize() ?: 0,
                    'path' => $storedPath,
                    'file_checksum' => $checksum,
                    'status' => $invalidRows === 0 ? 'validated' : 'validation_failed',
                    'total_rows' => count($parsedRows),
                    'valid_rows' => count($parsedRows) - $invalidRows,
                    'invalid_rows' => $invalidRows,
                    'validation_report' => ['error_counts' => collect($parsedRows)->pluck('validation_errors')->flatten()->countBy()->all()],
                    'submitted_by' => $actor->id,
                ]);

                foreach ($parsedRows as $row) {
                    $batch->rows()->create($row);
                }

                $this->auditLogger->record($actor, $batch, 'data_migration.staged', "Historical {$datasetType} migration staged with ".count($parsedRows).' rows.', null, ['file_checksum' => $checksum, 'valid_rows' => $batch->valid_rows, 'invalid_rows' => $batch->invalid_rows]);

                return $batch->load('rows.county');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(UploadedFile $file, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded source file is no longer available.']);
        }

        $sourceRows = $this->tabularImportReader->read($file);
        $header = array_shift($sourceRows)['values'] ?? null;
        if (! is_array($header)) {
            throw ValidationException::withMessages(['file' => 'The source header could not be read.']);
        }
        $normalizedHeader = array_map(fn (mixed $value): string => str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->ltrim("\xEF\xBB\xBF")->toString(), $header);
        if ($normalizedHeader !== self::REQUIRED_HEADERS) {
            throw ValidationException::withMessages(['file' => 'Use the required columns in this exact order: '.implode(', ', self::REQUIRED_HEADERS).'.']);
        }

        $counties = County::query()->get(['id', 'code'])->keyBy(fn (County $county): string => str_pad((string) $county->code, 3, '0', STR_PAD_LEFT));
        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            $values = $sourceRow['values'];
            if (count($rows) >= 5000) {
                throw ValidationException::withMessages(['file' => 'A migration batch may contain at most 5,000 data rows.']);
            }
            $values = array_pad(array_slice($values, 0, count(self::REQUIRED_HEADERS)), count(self::REQUIRED_HEADERS), null);
            $combinedPayload = array_combine(self::REQUIRED_HEADERS, array_map(fn (mixed $value): string => trim((string) $value), $values));
            /** @var array<string, string> $payload */
            $payload = $combinedPayload;
            $countyCode = str_pad($payload['county_code'], 3, '0', STR_PAD_LEFT);
            $county = $counties->get($countyCode);
            $period = $this->period($payload['period']);
            $errors = [];
            if (! $county instanceof County) {
                $errors[] = 'unknown_county_code';
            }
            if ($period === null) {
                $errors[] = 'invalid_period';
            } elseif ($period->lt($from) || $period->gt($to)) {
                $errors[] = 'period_outside_batch_range';
            }
            if ($payload['metric_code'] === '') {
                $errors[] = 'missing_metric_code';
            }
            if ($payload['metric_name'] === '') {
                $errors[] = 'missing_metric_name';
            }
            if ($payload['numeric_value'] === '' && $payload['narrative_value'] === '') {
                $errors[] = 'missing_metric_value';
            }
            if ($payload['numeric_value'] !== '' && ! is_numeric($payload['numeric_value'])) {
                $errors[] = 'invalid_numeric_value';
            }
            $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $rows[] = [
                'row_number' => $sourceRow['row_number'],
                'county_id' => $county?->id,
                'period' => $period,
                'metric_code' => $payload['metric_code'] !== '' ? Str::upper($payload['metric_code']) : null,
                'metric_name' => $payload['metric_name'] ?: null,
                'numeric_value' => $payload['numeric_value'] !== '' && is_numeric($payload['numeric_value']) ? $payload['numeric_value'] : null,
                'narrative_value' => $payload['narrative_value'] ?: null,
                'unit' => $payload['unit'] ?: null,
                'source_reference' => $payload['source_reference'] ?: null,
                'source_payload' => $payload,
                'source_checksum' => hash('sha256', $canonicalPayload),
                'validation_status' => $errors === [] ? 'valid' : 'invalid',
                'validation_errors' => $errors,
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The source file contains no data rows.']);
        }

        $seen = [];
        foreach ($rows as $index => $row) {
            if (! is_string($row['county_id']) || ! $row['period'] instanceof CarbonImmutable || ! is_string($row['metric_code'])) {
                continue;
            }

            $key = $this->naturalKey($row['county_id'], $row['period']->toDateString(), $row['metric_code']);
            if (array_key_exists($key, $seen)) {
                $rows[$index] = $this->addError($row, 'duplicate_natural_key_in_batch');
                $firstIndex = $seen[$key];
                $rows[$firstIndex] = $this->addError($rows[$firstIndex], 'duplicate_natural_key_in_batch');
            } else {
                $seen[$key] = $index;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function reconcileAppliedHistory(array $rows, string $datasetType): array
    {
        $countyIds = collect($rows)->pluck('county_id')->filter(fn (mixed $countyId): bool => is_string($countyId))->unique()->values();
        $periods = collect($rows)->pluck('period')->filter(fn (mixed $period): bool => $period instanceof CarbonImmutable)->map(fn (CarbonImmutable $period): string => $period->toDateString())->unique()->values();
        $metricCodes = collect($rows)->pluck('metric_code')->filter(fn (mixed $metricCode): bool => is_string($metricCode))->map(fn (string $code): string => Str::upper($code))->unique()->values();
        if ($countyIds->isEmpty() || $periods->isEmpty() || $metricCodes->isEmpty()) {
            return $rows;
        }

        $existing = HistoricalMetric::query()
            ->where('dataset_type', $datasetType)
            ->whereIn('county_id', $countyIds)
            ->whereIn('period', $periods)
            ->whereIn(DB::raw('upper(metric_code)'), $metricCodes)
            ->get(['county_id', 'period', 'metric_code', 'source_checksum'])
            ->keyBy(fn (HistoricalMetric $metric): string => $this->naturalKey($metric->county_id, $metric->period->toDateString(), $metric->metric_code));

        foreach ($rows as $index => $row) {
            if (! is_string($row['county_id']) || ! $row['period'] instanceof CarbonImmutable || ! is_string($row['metric_code'])) {
                continue;
            }

            $matched = $existing->get($this->naturalKey($row['county_id'], $row['period']->toDateString(), $row['metric_code']));
            if ($matched instanceof HistoricalMetric) {
                $error = hash_equals($matched->source_checksum, (string) $row['source_checksum']) ? 'duplicate_applied_record' : 'conflicting_applied_record';
                $rows[$index] = $this->addError($row, $error);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function addError(array $row, string $error): array
    {
        $errors = is_array($row['validation_errors']) ? $row['validation_errors'] : [];
        if (! in_array($error, $errors, true)) {
            $errors[] = $error;
        }
        $row['validation_errors'] = $errors;
        $row['validation_status'] = 'invalid';

        return $row;
    }

    private function naturalKey(string $countyId, string $period, string $metricCode): string
    {
        return implode('|', [$countyId, $period, Str::upper($metricCode)]);
    }

    private function period(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}$/', $value) === 1) {
            return CarbonImmutable::create((int) $value, 12, 31)->startOfDay();
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }
    }
}
