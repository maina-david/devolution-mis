<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\HistoricalMetric;
use App\Models\LegacyAcpaAssessment;
use App\Models\LegacyAcpaComponent;
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

    public const LEGACY_ACPA_HEADERS = ['county_code', 'assessment_reference', 'period', 'record_type', 'record_reference', 'criterion_code', 'title', 'numeric_value', 'maximum_value', 'status', 'assignment_role', 'person_identifier', 'person_name', 'description', 'decision', 'file_name', 'mime_type', 'file_checksum', 'source_reference'];

    public const LEGACY_ACPA_RECORD_TYPES = ['assessment', 'criterion_result', 'evidence_manifest', 'finding', 'assessor_assignment', 'appeal'];

    public function __construct(
        private AuditLogger $auditLogger,
        private TabularImportReader $tabularImportReader,
    ) {}

    public function handle(User $actor, UploadedFile $file, string $datasetType, string $sourceName, string $sourceReference, string $periodFrom, string $periodTo): DataMigrationBatch
    {
        $from = CarbonImmutable::parse($periodFrom)->startOfDay();
        $to = CarbonImmutable::parse($periodTo)->startOfDay();
        $parsedRows = $datasetType === 'acpa_reconstruction'
            ? $this->reconcileLegacyAcpaHistory($this->parseLegacyAcpa($file, $from, $to))
            : $this->reconcileAppliedHistory($this->parse($file, $from, $to), $datasetType);
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['file' => __('migration.import.source_unavailable')]);
        }

        $checksum = hash_file('sha256', $realPath);
        if ($checksum === false) {
            throw ValidationException::withMessages(['file' => __('migration.import.checksum_failed')]);
        }

        $storedPath = $file->storeAs('data-migrations', Str::uuid().'.'.$this->tabularImportReader->extension($file), 'local');
        if (! is_string($storedPath)) {
            throw ValidationException::withMessages(['file' => __('migration.import.private_store_failed')]);
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

                $this->auditLogger->record($actor, $batch, 'data_migration.staged', trans_choice('migration.audit.historical_staged', count($parsedRows), ['dataset' => $datasetType, 'count' => count($parsedRows)]), null, ['file_checksum' => $checksum, 'valid_rows' => $batch->valid_rows, 'invalid_rows' => $batch->invalid_rows]);

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
            throw ValidationException::withMessages(['file' => __('migration.import.source_unavailable')]);
        }

        $sourceRows = $this->tabularImportReader->read($file);
        $header = array_shift($sourceRows)['values'] ?? null;
        if (! is_array($header)) {
            throw ValidationException::withMessages(['file' => __('migration.import.header_unreadable')]);
        }
        $normalizedHeader = array_map(fn (mixed $value): string => str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->ltrim("\xEF\xBB\xBF")->toString(), $header);
        if ($normalizedHeader !== self::REQUIRED_HEADERS) {
            throw ValidationException::withMessages(['file' => __('migration.import.required_columns', ['columns' => implode(', ', self::REQUIRED_HEADERS)])]);
        }

        $counties = County::query()->get(['id', 'code'])->keyBy(fn (County $county): string => str_pad((string) $county->code, 3, '0', STR_PAD_LEFT));
        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            $values = $sourceRow['values'];
            if (count($rows) >= 5000) {
                throw ValidationException::withMessages(['file' => __('migration.import.migration_row_limit')]);
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
            throw ValidationException::withMessages(['file' => __('migration.import.no_rows')]);
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

    /** @return list<array<string, mixed>> */
    private function parseLegacyAcpa(UploadedFile $file, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['file' => __('migration.import.source_unavailable')]);
        }

        $sourceRows = $this->tabularImportReader->read($file);
        $header = array_shift($sourceRows)['values'] ?? null;
        if (! is_array($header)) {
            throw ValidationException::withMessages(['file' => __('migration.import.header_unreadable')]);
        }
        $normalizedHeader = array_map(fn (mixed $value): string => str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->ltrim("\xEF\xBB\xBF")->toString(), $header);
        if ($normalizedHeader !== self::LEGACY_ACPA_HEADERS) {
            throw ValidationException::withMessages(['file' => __('migration.import.legacy_acpa_columns', ['columns' => implode(', ', self::LEGACY_ACPA_HEADERS)])]);
        }

        $counties = County::query()->get(['id', 'code'])->keyBy(fn (County $county): string => str_pad((string) $county->code, 3, '0', STR_PAD_LEFT));
        $rows = [];
        foreach ($sourceRows as $sourceRow) {
            if (count($rows) >= 5000) {
                throw ValidationException::withMessages(['file' => __('migration.import.migration_row_limit')]);
            }
            $values = array_pad(array_slice($sourceRow['values'], 0, count(self::LEGACY_ACPA_HEADERS)), count(self::LEGACY_ACPA_HEADERS), null);
            $combined = array_combine(self::LEGACY_ACPA_HEADERS, array_map(fn (mixed $value): string => trim((string) $value), $values));
            /** @var array<string, string> $payload */
            $payload = $combined;
            $countyCode = str_pad($payload['county_code'], 3, '0', STR_PAD_LEFT);
            $county = $counties->get($countyCode);
            $period = $this->period($payload['period']);
            $recordType = Str::lower($payload['record_type']);
            $recordReference = $payload['record_reference'] !== '' ? $payload['record_reference'] : $payload['assessment_reference'];
            $payload['record_type'] = $recordType;
            $payload['record_reference'] = $recordReference;
            $errors = [];
            if (! $county instanceof County) {
                $errors[] = 'unknown_county_code';
            }
            if ($payload['assessment_reference'] === '') {
                $errors[] = 'missing_assessment_reference';
            }
            if ($period === null) {
                $errors[] = 'invalid_period';
            } elseif ($period->lt($from) || $period->gt($to)) {
                $errors[] = 'period_outside_batch_range';
            }
            if (! in_array($recordType, self::LEGACY_ACPA_RECORD_TYPES, true)) {
                $errors[] = 'invalid_record_type';
            }
            if ($recordReference === '') {
                $errors[] = 'missing_record_reference';
            }
            foreach (['numeric_value', 'maximum_value'] as $numericField) {
                if ($payload[$numericField] !== '' && ! is_numeric($payload[$numericField])) {
                    $errors[] = "invalid_{$numericField}";
                }
            }
            if ($recordType === 'assessment' && ($payload['title'] === '' || $payload['status'] === '' || $payload['numeric_value'] === '' || $payload['maximum_value'] === '')) {
                $errors[] = 'incomplete_assessment_header';
            }
            if ($recordType === 'criterion_result' && ($payload['criterion_code'] === '' || $payload['title'] === '' || $payload['numeric_value'] === '' || $payload['maximum_value'] === '')) {
                $errors[] = 'incomplete_criterion_result';
            }
            if ($recordType === 'evidence_manifest' && ($payload['criterion_code'] === '' || $payload['title'] === '' || $payload['file_name'] === '' || ! preg_match('/^[a-f0-9]{64}$/i', $payload['file_checksum']))) {
                $errors[] = 'incomplete_evidence_manifest';
            }
            if ($recordType === 'assessor_assignment' && ($payload['person_name'] === '' || $payload['person_identifier'] === '' || $payload['assignment_role'] === '')) {
                $errors[] = 'incomplete_assessor_assignment';
            }
            if ($recordType === 'finding' && ($payload['description'] === '' || $payload['status'] === '')) {
                $errors[] = 'incomplete_finding';
            }
            if ($recordType === 'appeal' && ($payload['description'] === '' || $payload['status'] === '')) {
                $errors[] = 'incomplete_appeal';
            }
            if ($recordType === 'appeal' && in_array($payload['status'], ['determined', 'closed'], true) && $payload['decision'] === '') {
                $errors[] = 'missing_appeal_decision';
            }

            $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $rows[] = [
                'row_number' => $sourceRow['row_number'],
                'county_id' => $county?->id,
                'period' => $period,
                'metric_code' => Str::upper(implode('|', [$payload['assessment_reference'], $recordType, $recordReference])),
                'metric_name' => $payload['title'] ?: Str::headline($recordType),
                'numeric_value' => $payload['numeric_value'] !== '' && is_numeric($payload['numeric_value']) ? $payload['numeric_value'] : null,
                'narrative_value' => $payload['description'] ?: null,
                'unit' => $payload['maximum_value'] ?: null,
                'source_reference' => $payload['source_reference'] ?: null,
                'source_payload' => $payload,
                'source_checksum' => hash('sha256', $canonicalPayload),
                'validation_status' => $errors === [] ? 'valid' : 'invalid',
                'validation_errors' => $errors,
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('migration.import.no_rows')]);
        }

        $parentsInBatch = collect($rows)->filter(fn (array $row): bool => $row['source_payload']['record_type'] === 'assessment' && is_string($row['county_id']))->map(fn (array $row): string => $row['county_id'].'|'.Str::upper($row['source_payload']['assessment_reference']))->all();
        $knownParents = array_flip($parentsInBatch);
        $seen = [];
        foreach ($rows as $index => $row) {
            $payload = $row['source_payload'];
            $key = ($row['county_id'] ?? '').'|'.Str::upper($payload['assessment_reference']).'|'.Str::lower($payload['record_type']).'|'.Str::upper($payload['record_reference'] ?: $payload['assessment_reference']);
            if (isset($seen[$key])) {
                $rows[$index] = $this->addError($row, 'duplicate_natural_key_in_batch');
                $rows[$seen[$key]] = $this->addError($rows[$seen[$key]], 'duplicate_natural_key_in_batch');
            } else {
                $seen[$key] = $index;
            }
            $parentKey = ($row['county_id'] ?? '').'|'.Str::upper($payload['assessment_reference']);
            if ($payload['record_type'] !== 'assessment' && ! isset($knownParents[$parentKey])) {
                $rows[$index] = $this->addError($rows[$index], 'missing_assessment_header');
            }
        }

        return $this->validateLegacyAcpaRelationships($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function validateLegacyAcpaRelationships(array $rows): array
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];
        foreach ($rows as $index => $row) {
            $payload = $row['source_payload'];
            if (! is_string($row['county_id']) || $payload['assessment_reference'] === '') {
                continue;
            }

            $groups[$row['county_id'].'|'.Str::upper($payload['assessment_reference'])][] = $index;
        }

        foreach ($groups as $indexes) {
            $headerIndexes = array_values(array_filter($indexes, fn (int $index): bool => $rows[$index]['source_payload']['record_type'] === 'assessment'));
            if (count($headerIndexes) !== 1) {
                continue;
            }

            $headerIndex = $headerIndexes[0];
            $headerPayload = $rows[$headerIndex]['source_payload'];
            $criterionIndexes = array_values(array_filter($indexes, fn (int $index): bool => $rows[$index]['source_payload']['record_type'] === 'criterion_result'));
            $evidenceIndexes = array_values(array_filter($indexes, fn (int $index): bool => $rows[$index]['source_payload']['record_type'] === 'evidence_manifest'));
            $assessorIndexes = array_values(array_filter($indexes, fn (int $index): bool => $rows[$index]['source_payload']['record_type'] === 'assessor_assignment'));
            $periods = array_values(array_unique(array_map(
                fn (int $index): string => $rows[$index]['period'] instanceof CarbonImmutable ? $rows[$index]['period']->toDateString() : '',
                $indexes,
            )));
            if (count($periods) > 1) {
                foreach ($indexes as $index) {
                    $rows[$index] = $this->addError($rows[$index], 'inconsistent_assessment_period');
                }
            }

            if ($criterionIndexes === []) {
                $rows[$headerIndex] = $this->addError($rows[$headerIndex], 'missing_criterion_results');
            }
            if ($evidenceIndexes === []) {
                $rows[$headerIndex] = $this->addError($rows[$headerIndex], 'missing_evidence_manifests');
            }
            if ($assessorIndexes === []) {
                $rows[$headerIndex] = $this->addError($rows[$headerIndex], 'missing_assessor_assignments');
            }

            $criterionCodes = [];
            $criterionScore = 0.0;
            $criterionMaximum = 0.0;
            foreach ($criterionIndexes as $index) {
                $payload = $rows[$index]['source_payload'];
                $criterionCode = Str::upper($payload['criterion_code']);
                $criterionCodes[$criterionCode] = $index;
                if (! is_numeric($payload['numeric_value']) || ! is_numeric($payload['maximum_value'])) {
                    continue;
                }

                $score = (float) $payload['numeric_value'];
                $maximum = (float) $payload['maximum_value'];
                if ($score < 0 || $maximum <= 0 || $score > $maximum) {
                    $rows[$index] = $this->addError($rows[$index], 'criterion_score_out_of_range');
                }
                $criterionScore += $score;
                $criterionMaximum += $maximum;
            }

            if (is_numeric($headerPayload['numeric_value']) && abs($criterionScore - (float) $headerPayload['numeric_value']) > 0.0001) {
                $rows[$headerIndex] = $this->addError($rows[$headerIndex], 'criterion_score_total_mismatch');
            }
            if (is_numeric($headerPayload['maximum_value']) && abs($criterionMaximum - (float) $headerPayload['maximum_value']) > 0.0001) {
                $rows[$headerIndex] = $this->addError($rows[$headerIndex], 'criterion_maximum_total_mismatch');
            }

            $evidenceCriterionCodes = [];
            $evidenceChecksums = [];
            foreach ($evidenceIndexes as $index) {
                $payload = $rows[$index]['source_payload'];
                $criterionCode = Str::upper($payload['criterion_code']);
                $evidenceCriterionCodes[$criterionCode] = true;
                if ($criterionCode !== '' && ! array_key_exists($criterionCode, $criterionCodes)) {
                    $rows[$index] = $this->addError($rows[$index], 'unknown_criterion_reference');
                }
                $checksum = Str::lower($payload['file_checksum']);
                if ($checksum !== '' && isset($evidenceChecksums[$checksum])) {
                    $rows[$index] = $this->addError($rows[$index], 'duplicate_evidence_checksum');
                    $firstIndex = $evidenceChecksums[$checksum];
                    $rows[$firstIndex] = $this->addError($rows[$firstIndex], 'duplicate_evidence_checksum');
                } elseif ($checksum !== '') {
                    $evidenceChecksums[$checksum] = $index;
                }
            }
            foreach ($criterionCodes as $criterionCode => $index) {
                if (! isset($evidenceCriterionCodes[$criterionCode])) {
                    $rows[$index] = $this->addError($rows[$index], 'missing_evidence_manifest');
                }
            }

            foreach ($indexes as $index) {
                $payload = $rows[$index]['source_payload'];
                if (in_array($payload['record_type'], ['finding', 'appeal'], true)
                    && $payload['criterion_code'] !== ''
                    && ! array_key_exists(Str::upper($payload['criterion_code']), $criterionCodes)) {
                    $rows[$index] = $this->addError($rows[$index], 'unknown_criterion_reference');
                }
            }

            $leadAssessors = array_filter($assessorIndexes, fn (int $index): bool => Str::lower($rows[$index]['source_payload']['assignment_role']) === 'lead_assessor');
            if (count($leadAssessors) === 0) {
                $rows[$headerIndex] = $this->addError($rows[$headerIndex], 'missing_lead_assessor');
            } elseif (count($leadAssessors) > 1) {
                foreach ($leadAssessors as $index) {
                    $rows[$index] = $this->addError($rows[$index], 'multiple_lead_assessors');
                }
            }

            $personIdentifiers = [];
            foreach ($assessorIndexes as $index) {
                $identifier = Str::upper($rows[$index]['source_payload']['person_identifier']);
                if ($identifier !== '' && isset($personIdentifiers[$identifier])) {
                    $rows[$index] = $this->addError($rows[$index], 'duplicate_assessor_identifier');
                    $firstIndex = $personIdentifiers[$identifier];
                    $rows[$firstIndex] = $this->addError($rows[$firstIndex], 'duplicate_assessor_identifier');
                } elseif ($identifier !== '') {
                    $personIdentifiers[$identifier] = $index;
                }
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function reconcileLegacyAcpaHistory(array $rows): array
    {
        foreach ($rows as $index => $row) {
            if (! is_string($row['county_id'])) {
                continue;
            }
            $payload = $row['source_payload'];
            $assessment = LegacyAcpaAssessment::query()->where('county_id', $row['county_id'])->whereRaw('upper(assessment_reference) = ?', [Str::upper($payload['assessment_reference'])])->first();
            if ($payload['record_type'] === 'assessment' && $assessment instanceof LegacyAcpaAssessment) {
                $rows[$index] = $this->addError($row, hash_equals($assessment->source_checksum, $row['source_checksum']) ? 'duplicate_applied_record' : 'conflicting_applied_record');
            } elseif ($payload['record_type'] !== 'assessment' && $assessment instanceof LegacyAcpaAssessment) {
                $component = LegacyAcpaComponent::query()->where('legacy_acpa_assessment_id', $assessment->id)->where('record_type', $payload['record_type'])->whereRaw('upper(record_reference) = ?', [Str::upper($payload['record_reference'])])->first();
                if ($component instanceof LegacyAcpaComponent) {
                    $rows[$index] = $this->addError($row, hash_equals($component->source_checksum, $row['source_checksum']) ? 'duplicate_applied_record' : 'conflicting_applied_record');
                }
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
