<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TabularImportReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StageReferenceDataImport
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'organizations' => ['code', 'name', 'type', 'county_code', 'email', 'status'],
        'sectors' => ['code', 'name', 'parent_sector_code', 'description', 'is_active'],
        'programmes' => ['code', 'name', 'description', 'lead_organization_code', 'sector_code', 'starts_on', 'ends_on', 'status', 'budget_amount', 'currency'],
        'users' => ['name', 'email', 'role', 'home_county_code', 'assigned_county_codes'],
    ];

    public function __construct(
        private AuditLogger $auditLogger,
        private TabularImportReader $tabularImportReader,
    ) {}

    public function handle(User $actor, UploadedFile $file, string $datasetType, string $sourceName, string $sourceReference): DataMigrationBatch
    {
        abort_unless(array_key_exists($datasetType, self::HEADERS), 422, 'The selected bulk-import dataset is not supported.');
        abort_if($datasetType === 'users' && ! $actor->can(ProgrammePermission::ManageUserAccess->value), 403, 'Only platform access administrators may stage user imports.');
        $rows = $this->parse($file, $datasetType);
        $realPath = $file->getRealPath();

        if ($realPath === false || ($checksum = hash_file('sha256', $realPath)) === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded source checksum could not be calculated.']);
        }

        $storedPath = $file->storeAs('data-migrations', Str::uuid().'.'.$this->tabularImportReader->extension($file), 'local');
        if (! is_string($storedPath)) {
            throw ValidationException::withMessages(['file' => 'The source file could not be stored privately.']);
        }

        try {
            return DB::transaction(function () use ($actor, $file, $datasetType, $sourceName, $sourceReference, $rows, $checksum, $storedPath): DataMigrationBatch {
                $invalidRows = collect($rows)->where('validation_status', 'invalid')->count();
                $batch = DataMigrationBatch::create([
                    'reference' => 'IMP-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'dataset_type' => $datasetType,
                    'source_name' => $sourceName,
                    'source_reference' => $sourceReference,
                    'period_from' => today(),
                    'period_to' => today(),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'text/csv',
                    'size_bytes' => $file->getSize() ?: 0,
                    'path' => $storedPath,
                    'file_checksum' => $checksum,
                    'status' => $invalidRows === 0 ? 'validated' : 'validation_failed',
                    'total_rows' => count($rows),
                    'valid_rows' => count($rows) - $invalidRows,
                    'invalid_rows' => $invalidRows,
                    'validation_report' => ['error_counts' => collect($rows)->pluck('validation_errors')->flatten()->countBy()->all()],
                    'submitted_by' => $actor->id,
                ]);

                foreach ($rows as $row) {
                    $batch->rows()->create($row);
                }

                $this->auditLogger->record($actor, $batch, 'data_import.staged', "Bulk {$datasetType} import staged with ".count($rows).' rows.', metadata: [
                    'file_checksum' => $checksum,
                    'valid_rows' => $batch->valid_rows,
                    'invalid_rows' => $batch->invalid_rows,
                ]);

                return $batch->load('rows');
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    private function parse(UploadedFile $file, string $datasetType): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded source file is no longer available.']);
        }

        $sourceRows = $this->tabularImportReader->read($file);
        $header = array_shift($sourceRows)['values'] ?? null;
        $expected = self::HEADERS[$datasetType];
        $normalized = is_array($header)
            ? array_map(fn (mixed $value): string => str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->ltrim("\xEF\xBB\xBF")->toString(), $header)
            : [];

        if ($normalized !== $expected) {
            throw ValidationException::withMessages(['file' => 'Use the required columns in this exact order: '.implode(', ', $expected).'.']);
        }

        $existingCodes = match ($datasetType) {
            'organizations' => Organization::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'sectors' => Sector::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'programmes' => Programme::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'users' => User::query()->withTrashed()->pluck('email')->map(fn (string $email): string => Str::lower($email))->all(),
            default => throw ValidationException::withMessages(['dataset_type' => 'The selected bulk-import dataset is not supported.']),
        };
        $rows = [];
        $seenCodes = [];
        foreach ($sourceRows as $sourceRow) {
            $values = $sourceRow['values'];
            if (count($rows) >= 5000) {
                throw ValidationException::withMessages(['file' => 'A bulk-import batch may contain at most 5,000 data rows.']);
            }

            $values = array_pad(array_slice($values, 0, count($expected)), count($expected), null);
            /** @var array<string, string> $payload */
            $payload = array_combine($expected, array_map(fn (mixed $value): string => trim((string) $value), $values));
            $identity = $datasetType === 'users' ? Str::lower($payload['email']) : Str::upper($payload['code']);
            if ($datasetType === 'users') {
                $payload['email'] = $identity;
            } else {
                $payload['code'] = $identity;
            }
            $errors = $this->validatePayload($datasetType, $payload);

            if (in_array($identity, $existingCodes, true)) {
                $errors[] = $datasetType === 'users' ? 'email_already_exists' : 'code_already_exists';
            }
            if (in_array($identity, $seenCodes, true)) {
                $errors[] = $datasetType === 'users' ? 'duplicate_email_in_file' : 'duplicate_code_in_file';
            }
            $seenCodes[] = $identity;
            $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $rows[] = [
                'row_number' => $sourceRow['row_number'],
                'source_payload' => $payload,
                'source_checksum' => hash('sha256', $canonicalPayload),
                'validation_status' => $errors === [] ? 'valid' : 'invalid',
                'validation_errors' => array_values(array_unique($errors)),
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'The source file contains no data rows.']);
        }

        $identityKey = $datasetType === 'users' ? 'email' : 'code';
        $duplicateCodes = collect($rows)
            ->pluck("source_payload.{$identityKey}")
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys();

        foreach ($rows as $index => $row) {
            if (! $duplicateCodes->contains($row['source_payload'][$identityKey])) {
                continue;
            }

            $duplicateError = $datasetType === 'users' ? 'duplicate_email_in_file' : 'duplicate_code_in_file';
            $rows[$index]['validation_errors'] = array_values(array_unique([...$row['validation_errors'], $duplicateError]));
            $rows[$index]['validation_status'] = 'invalid';
        }

        return $rows;
    }

    /** @param array<string, string> $payload
     * @return list<string>
     */
    private function validatePayload(string $datasetType, array $payload): array
    {
        $errors = [];
        if ($datasetType !== 'users' && $payload['code'] === '') {
            $errors[] = 'missing_code';
        }
        if ($payload['name'] === '') {
            $errors[] = 'missing_name';
        }

        if ($datasetType === 'organizations') {
            if (! in_array($payload['type'], ['national', 'county', 'partner', 'oversight', 'training'], true)) {
                $errors[] = 'invalid_organization_type';
            }
            if ($payload['county_code'] !== '' && ! County::query()->where('code', (int) $payload['county_code'])->exists()) {
                $errors[] = 'unknown_county_code';
            }
            if ($payload['email'] !== '' && filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'invalid_email';
            }
            if (! in_array($payload['status'], ['active', 'inactive'], true)) {
                $errors[] = 'invalid_status';
            }
        }

        if ($datasetType === 'sectors') {
            if ($payload['parent_sector_code'] !== '' && ! Sector::query()->whereRaw('upper(code) = ?', [Str::upper($payload['parent_sector_code'])])->exists()) {
                $errors[] = 'unknown_parent_sector_code';
            }
            if (! in_array(Str::lower($payload['is_active']), ['1', '0', 'true', 'false', 'yes', 'no'], true)) {
                $errors[] = 'invalid_active_flag';
            }
        }

        if ($datasetType === 'programmes') {
            if ($payload['lead_organization_code'] !== '' && ! Organization::query()->whereRaw('upper(code) = ?', [Str::upper($payload['lead_organization_code'])])->exists()) {
                $errors[] = 'unknown_lead_organization_code';
            }
            if ($payload['sector_code'] !== '' && ! Sector::query()->whereRaw('upper(code) = ?', [Str::upper($payload['sector_code'])])->exists()) {
                $errors[] = 'unknown_sector_code';
            }
            if ($payload['starts_on'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['starts_on']) !== 1) {
                $errors[] = 'invalid_start_date';
            }
            if ($payload['ends_on'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['ends_on']) !== 1) {
                $errors[] = 'invalid_end_date';
            }
            if (! in_array($payload['status'], ['planned', 'active', 'completed', 'suspended'], true)) {
                $errors[] = 'invalid_status';
            }
            if ($payload['budget_amount'] !== '' && (! is_numeric($payload['budget_amount']) || (float) $payload['budget_amount'] < 0)) {
                $errors[] = 'invalid_budget_amount';
            }
            if (preg_match('/^[A-Z]{3}$/', Str::upper($payload['currency'])) !== 1) {
                $errors[] = 'invalid_currency';
            }
        }

        if ($datasetType === 'users') {
            $role = UserRole::tryFrom($payload['role']);
            if (filter_var($payload['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'invalid_email';
            }
            if ($role === null) {
                $errors[] = 'invalid_role';

                return $errors;
            }

            $homeCounty = $payload['home_county_code'] !== ''
                ? County::query()->where('code', (int) $payload['home_county_code'])->first()
                : null;
            if (in_array($role, [UserRole::CountyOfficial, UserRole::CountyAdmin], true) && $homeCounty === null) {
                $errors[] = 'valid_home_county_required';
            }
            if (! in_array($role, [UserRole::CountyOfficial, UserRole::CountyAdmin], true) && $payload['home_county_code'] !== '') {
                $errors[] = 'home_county_not_allowed_for_role';
            }

            $assignedCodes = $this->countyCodes($payload['assigned_county_codes']);
            $knownAssignedCount = $assignedCodes === [] ? 0 : County::query()->whereIn('code', $assignedCodes)->count();
            if ($role->hasAssignedCountyScope() && ($assignedCodes === [] || $knownAssignedCount !== count($assignedCodes))) {
                $errors[] = 'valid_assigned_counties_required';
            }
            if (! $role->hasAssignedCountyScope() && $assignedCodes !== []) {
                $errors[] = 'assigned_counties_not_allowed_for_role';
            }
        }

        return $errors;
    }

    /** @return list<int> */
    private function countyCodes(string $value): array
    {
        return array_values(collect(preg_split('/[|;,]/', $value) ?: [])
            ->map(fn (string $code): string => trim($code))
            ->filter()
            ->map(fn (string $code): int => (int) $code)
            ->unique()
            ->values()
            ->all());
    }
}
