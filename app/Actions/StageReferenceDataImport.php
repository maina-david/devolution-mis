<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\Sector;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use App\Services\AuditLogger;
use App\Services\TabularImportReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @phpstan-type ProgrammeCoverageContext array{
 *     programmes: array<string, array{id: string, starts_on: string|null, ends_on: string|null}>,
 *     counties: array<int, string>,
 *     implementation_leads: array<int, string>,
 *     existing_coverages: array<string, array<int, array{starts_on: string, ends_on: string|null}>>
 * }
 */
class StageReferenceDataImport
{
    /** @var array<string, list<string>> */
    public const HEADERS = [
        'counties' => ['code', 'name', 'region', 'official_website_url', 'map_x', 'map_y'],
        'organizations' => ['code', 'name', 'type', 'county_code', 'email', 'status'],
        'sectors' => ['code', 'name', 'parent_sector_code', 'description', 'is_active'],
        'programmes' => ['code', 'name', 'description', 'lead_organization_code', 'sector_code', 'starts_on', 'ends_on', 'status', 'budget_amount', 'currency'],
        'programme_county_coverages' => ['programme_code', 'county_code', 'implementation_lead_code', 'starts_on', 'ends_on', 'status', 'funding_allocation', 'currency', 'source_reference', 'notes'],
        'users' => ['name', 'email', 'role', 'home_county_code', 'assigned_county_codes'],
        'sub_counties' => ['county_code', 'code', 'name', 'effective_from', 'effective_to', 'source_checksum_sha256', 'boundary_geojson', 'boundary_checksum_sha256'],
        'wards' => ['sub_county_code', 'code', 'name', 'effective_from', 'effective_to', 'source_checksum_sha256', 'boundary_geojson', 'boundary_checksum_sha256'],
    ];

    public function __construct(
        private AuditLogger $auditLogger,
        private TabularImportReader $tabularImportReader,
    ) {}

    public function handle(User $actor, UploadedFile $file, string $datasetType, string $sourceName, string $sourceReference): DataMigrationBatch
    {
        abort_unless(array_key_exists($datasetType, self::HEADERS), 422, 'The selected bulk-import dataset is not supported.');
        if ($datasetType === 'users') {
            abort_unless($actor->can(ProgrammePermission::ManageUserAccess->value), 403, 'Only platform access administrators may stage user imports.');
        } else {
            abort_unless($actor->can(ProgrammePermission::ManageReferenceData->value), 403, 'Only reference-data managers may stage governed registry imports.');
        }
        $rows = $this->parse($file, $datasetType);
        $realPath = $file->getRealPath();

        if ($realPath === false || ($checksum = hash_file('sha256', $realPath)) === false) {
            throw ValidationException::withMessages(['file' => __('migration.import.upload_checksum_failed')]);
        }

        $storedPath = $file->storeAs('data-migrations', Str::uuid().'.'.$this->tabularImportReader->extension($file), 'local');
        if (! is_string($storedPath)) {
            throw ValidationException::withMessages(['file' => __('migration.import.private_store_failed')]);
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

    private function validDate(string $value): bool
    {
        return $value !== '' && CarbonImmutable::hasFormat($value, 'Y-m-d');
    }

    /** @return list<array<string, mixed>> */
    private function parse(UploadedFile $file, string $datasetType): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages(['file' => __('migration.import.source_unavailable')]);
        }

        $sourceRows = $this->tabularImportReader->read($file);
        $header = array_shift($sourceRows)['values'] ?? null;
        $expected = self::HEADERS[$datasetType];
        $normalized = is_array($header)
            ? array_map(fn (mixed $value): string => str((string) $value)->trim()->lower()->replace([' ', '-'], '_')->ltrim("\xEF\xBB\xBF")->toString(), $header)
            : [];

        if ($normalized !== $expected) {
            throw ValidationException::withMessages(['file' => __('migration.import.required_columns', ['columns' => implode(', ', $expected)])]);
        }

        $existingCodes = match ($datasetType) {
            'counties' => County::query()->withTrashed()->pluck('code')->map(fn (int $code): string => (string) $code)->all(),
            'organizations' => Organization::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'sectors' => Sector::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'programmes' => Programme::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'sub_counties' => SubCounty::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'wards' => Ward::query()->withTrashed()->pluck('code')->map(fn (string $code): string => Str::upper($code))->all(),
            'programme_county_coverages' => [],
            'users' => User::query()->withTrashed()->pluck('email')->map(fn (string $email): string => Str::lower($email))->all(),
            default => throw ValidationException::withMessages(['dataset_type' => __('migration.import.unsupported_dataset')]),
        };
        $coverageContext = $datasetType === 'programme_county_coverages' ? $this->programmeCoverageContext() : null;
        $rows = [];
        $seenIdentities = [];
        foreach ($sourceRows as $sourceRow) {
            $values = $sourceRow['values'];
            if (count($rows) >= 5000) {
                throw ValidationException::withMessages(['file' => __('migration.import.bulk_row_limit')]);
            }

            $values = array_pad(array_slice($values, 0, count($expected)), count($expected), null);
            /** @var array<string, string> $payload */
            $payload = array_combine($expected, array_map(fn (mixed $value): string => trim((string) $value), $values));
            if ($datasetType === 'programme_county_coverages') {
                $payload['programme_code'] = Str::upper($payload['programme_code']);
                $payload['implementation_lead_code'] = Str::upper($payload['implementation_lead_code']);
                $payload['currency'] = Str::upper($payload['currency']);
                if (ctype_digit($payload['county_code'])) {
                    $payload['county_code'] = str_pad((string) ((int) $payload['county_code']), 3, '0', STR_PAD_LEFT);
                }
                $identity = $this->coverageIdentity($payload);
            } elseif ($datasetType === 'users') {
                $identity = Str::lower($payload['email']);
                $payload['email'] = $identity;
            } elseif ($datasetType === 'counties') {
                $identity = (string) ((int) $payload['code']);
                $payload['code'] = $identity;
            } else {
                $identity = Str::upper($payload['code']);
                $payload['code'] = $identity;
            }
            $errors = $this->validatePayload($datasetType, $payload, $coverageContext);

            if ($datasetType !== 'programme_county_coverages' && in_array($identity, $existingCodes, true)) {
                $errors[] = $datasetType === 'users' ? 'email_already_exists' : 'code_already_exists';
            }
            if (in_array($identity, $seenIdentities, true)) {
                $errors[] = match ($datasetType) {
                    'users' => 'duplicate_email_in_file',
                    'programme_county_coverages' => 'duplicate_coverage_in_file',
                    default => 'duplicate_code_in_file',
                };
            }
            $seenIdentities[] = $identity;
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
            throw ValidationException::withMessages(['file' => __('migration.import.no_rows')]);
        }

        $identities = collect($rows)->map(fn (array $row): string => $datasetType === 'programme_county_coverages'
            ? $this->coverageIdentity($row['source_payload'])
            : (string) data_get($row, 'source_payload.'.($datasetType === 'users' ? 'email' : 'code')));
        $duplicateIdentities = $identities
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->keys();

        foreach ($rows as $index => $row) {
            if (! $duplicateIdentities->contains($identities[$index])) {
                continue;
            }

            $duplicateError = match ($datasetType) {
                'users' => 'duplicate_email_in_file',
                'programme_county_coverages' => 'duplicate_coverage_in_file',
                default => 'duplicate_code_in_file',
            };
            $rows[$index]['validation_errors'] = array_values(array_unique([...$row['validation_errors'], $duplicateError]));
            $rows[$index]['validation_status'] = 'invalid';
        }

        if ($datasetType === 'programme_county_coverages') {
            $rows = $this->markOverlappingCoverageRows($rows);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $payload
     * @param  ProgrammeCoverageContext|null  $coverageContext
     * @return list<string>
     */
    private function validatePayload(string $datasetType, array $payload, ?array $coverageContext = null): array
    {
        if ($datasetType === 'programme_county_coverages') {
            if ($coverageContext === null) {
                throw new \LogicException('Programme coverage validation context is required.');
            }

            return $this->validateProgrammeCoveragePayload($payload, $coverageContext);
        }

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
        if ($datasetType === 'sub_counties' || $datasetType === 'wards') {
            $parentField = $datasetType === 'sub_counties' ? 'county_code' : 'sub_county_code';
            $parentExists = $datasetType === 'sub_counties'
                ? County::query()->where('code', (int) $payload[$parentField])->exists()
                : SubCounty::query()->whereRaw('upper(code) = ?', [Str::upper($payload[$parentField])])->exists();
            if ($payload[$parentField] === '' || ! $parentExists) {
                $errors[] = $datasetType === 'sub_counties' ? 'unknown_county_code' : 'unknown_sub_county_code';
            }
            if (! preg_match('/^[a-f0-9]{64}$/i', $payload['source_checksum_sha256'])) {
                $errors[] = 'invalid_source_checksum';
            }
            if ($payload['boundary_checksum_sha256'] !== '' && ! preg_match('/^[a-f0-9]{64}$/i', $payload['boundary_checksum_sha256'])) {
                $errors[] = 'invalid_boundary_checksum';
            }
            if ($payload['boundary_geojson'] !== '' && ! is_array(json_decode($payload['boundary_geojson'], true))) {
                $errors[] = 'invalid_boundary_geojson';
            }
            if (! $this->validDate($payload['effective_from']) || ($payload['effective_to'] !== '' && (! $this->validDate($payload['effective_to']) || $payload['effective_to'] < $payload['effective_from']))) {
                $errors[] = 'invalid_effective_period';
            }
        }

        if ($datasetType === 'counties') {
            if (! ctype_digit($payload['code']) || (int) $payload['code'] < 1 || (int) $payload['code'] > 999) {
                $errors[] = 'invalid_county_code';
            }
            if ($payload['official_website_url'] !== '' && filter_var($payload['official_website_url'], FILTER_VALIDATE_URL) === false) {
                $errors[] = 'invalid_official_website_url';
            }
            foreach (['map_x', 'map_y'] as $coordinate) {
                if (! is_numeric($payload[$coordinate]) || (float) $payload[$coordinate] < 0 || (float) $payload[$coordinate] > 100) {
                    $errors[] = "invalid_{$coordinate}";
                }
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

    /** @return ProgrammeCoverageContext */
    private function programmeCoverageContext(): array
    {
        $programmes = Programme::query()->get(['id', 'code', 'starts_on', 'ends_on'])->mapWithKeys(fn (Programme $programme): array => [
            Str::upper($programme->code) => [
                'id' => $programme->id,
                'starts_on' => $programme->starts_on?->toDateString(),
                'ends_on' => $programme->ends_on?->toDateString(),
            ],
        ])->all();
        $counties = County::query()->pluck('id', 'code')->mapWithKeys(fn (string $id, int|string $code): array => [(int) $code => $id])->all();
        $implementationLeads = Organization::query()->where('status', 'active')->pluck('code')->map(fn (string $code): string => Str::upper($code))->values()->all();
        $existingCoverages = ProgrammeCountyCoverage::query()->get(['programme_id', 'county_id', 'starts_on', 'ends_on'])
            ->groupBy(fn (ProgrammeCountyCoverage $coverage): string => "{$coverage->programme_id}|{$coverage->county_id}")
            ->map(fn ($coverages): array => $coverages->map(fn (ProgrammeCountyCoverage $coverage): array => [
                'starts_on' => $coverage->starts_on->toDateString(),
                'ends_on' => $coverage->ends_on?->toDateString(),
            ])->values()->all())
            ->all();

        return [
            'programmes' => $programmes,
            'counties' => $counties,
            'implementation_leads' => $implementationLeads,
            'existing_coverages' => $existingCoverages,
        ];
    }

    /**
     * @param  array<string, string>  $payload
     * @param  ProgrammeCoverageContext  $context
     * @return list<string>
     */
    private function validateProgrammeCoveragePayload(array $payload, array $context): array
    {
        $errors = [];
        $programme = $context['programmes'][$payload['programme_code']] ?? null;
        $countyCode = ctype_digit($payload['county_code']) ? (int) $payload['county_code'] : null;
        $countyId = $countyCode !== null ? ($context['counties'][$countyCode] ?? null) : null;

        if ($payload['programme_code'] === '' || ! is_array($programme)) {
            $errors[] = 'unknown_programme_code';
        }
        if ($countyId === null) {
            $errors[] = 'unknown_county_code';
        }
        if ($payload['implementation_lead_code'] !== '' && ! in_array($payload['implementation_lead_code'], $context['implementation_leads'], true)) {
            $errors[] = 'unknown_active_implementation_lead_code';
        }

        $validStart = $this->isIsoDate($payload['starts_on']);
        $validEnd = $payload['ends_on'] === '' || $this->isIsoDate($payload['ends_on']);
        if (! $validStart) {
            $errors[] = 'invalid_start_date';
        }
        if (! $validEnd) {
            $errors[] = 'invalid_end_date';
        }
        if ($validStart && $validEnd && $payload['ends_on'] !== '' && $payload['ends_on'] < $payload['starts_on']) {
            $errors[] = 'end_date_before_start_date';
        }
        if (is_array($programme) && $validStart && is_string($programme['starts_on']) && $payload['starts_on'] < $programme['starts_on']) {
            $errors[] = 'coverage_before_programme_start';
        }
        if (is_array($programme) && is_string($programme['ends_on']) && ($payload['ends_on'] === '' || ($validEnd && $payload['ends_on'] > $programme['ends_on']))) {
            $errors[] = 'coverage_after_programme_end';
        }
        if (! in_array($payload['status'], ['planned', 'active', 'paused', 'closed'], true)) {
            $errors[] = 'invalid_status';
        }
        if ($payload['funding_allocation'] !== '' && (! is_numeric($payload['funding_allocation']) || (float) $payload['funding_allocation'] < 0)) {
            $errors[] = 'invalid_funding_allocation';
        }
        if (preg_match('/^[A-Z]{3}$/', $payload['currency']) !== 1) {
            $errors[] = 'invalid_currency';
        }
        if ($payload['source_reference'] === '' || Str::length($payload['source_reference']) > 255) {
            $errors[] = 'invalid_source_reference';
        }
        if (Str::length($payload['notes']) > 5000) {
            $errors[] = 'notes_too_long';
        }

        if (is_array($programme) && is_string($countyId) && $validStart && $validEnd) {
            $existing = $context['existing_coverages'][$programme['id'].'|'.$countyId] ?? [];
            foreach ($existing as $coverage) {
                if ($this->rangesOverlap($payload['starts_on'], $payload['ends_on'] ?: null, $coverage['starts_on'], $coverage['ends_on'])) {
                    $errors[] = 'overlapping_existing_coverage';
                    break;
                }
            }
        }

        return $errors;
    }

    /** @param array<string, string> $payload */
    private function coverageIdentity(array $payload): string
    {
        return implode('|', [$payload['programme_code'], $payload['county_code'], $payload['starts_on'], $payload['ends_on'] ?: 'open']);
    }

    /** @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function markOverlappingCoverageRows(array $rows): array
    {
        $groups = collect($rows)->keys()->groupBy(fn (int $index): string => implode('|', [
            data_get($rows[$index], 'source_payload.programme_code'),
            data_get($rows[$index], 'source_payload.county_code'),
        ]));

        foreach ($groups as $indices) {
            $sorted = $indices->filter(fn (int $index): bool => $this->isIsoDate((string) data_get($rows[$index], 'source_payload.starts_on')))
                ->sortBy(fn (int $index): string => (string) data_get($rows[$index], 'source_payload.starts_on'));
            $furthestEnd = null;
            $furthestIndex = null;
            foreach ($sorted as $index) {
                $start = (string) data_get($rows[$index], 'source_payload.starts_on');
                $end = (string) data_get($rows[$index], 'source_payload.ends_on') ?: '9999-12-31';
                if ($furthestEnd !== null && $start <= $furthestEnd) {
                    foreach ([$furthestIndex, $index] as $overlapIndex) {
                        $rows[$overlapIndex]['validation_errors'] = array_values(array_unique([...$rows[$overlapIndex]['validation_errors'], 'overlapping_coverage_in_file']));
                        $rows[$overlapIndex]['validation_status'] = 'invalid';
                    }
                }
                if ($furthestEnd === null || $end > $furthestEnd) {
                    $furthestEnd = $end;
                    $furthestIndex = $index;
                }
            }
        }

        return $rows;
    }

    private function isIsoDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private function rangesOverlap(string $leftStart, ?string $leftEnd, string $rightStart, ?string $rightEnd): bool
    {
        return $leftStart <= ($rightEnd ?? '9999-12-31') && ($leftEnd ?? '9999-12-31') >= $rightStart;
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
