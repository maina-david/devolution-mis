<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\DataMigrationRow;
use App\Models\HistoricalMetric;
use App\Models\LegacyAcpaAssessment;
use App\Models\LegacyAcpaComponent;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\Sector;
use App\Models\SubCounty;
use App\Models\User;
use App\Models\Ward;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyHistoricalDataMigration
{
    public function __construct(
        private AuditLogger $auditLogger,
        private GrantProgrammeAccess $grantProgrammeAccess,
        private CreateReferenceDataRelease $createReferenceDataRelease,
        private CreateProgrammeCountyCoverage $createProgrammeCountyCoverage,
    ) {}

    public function handle(DataMigrationBatch $batch, User $actor): DataMigrationBatch
    {
        return DB::transaction(function () use ($batch, $actor): DataMigrationBatch {
            $locked = DataMigrationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($locked->status === 'approved', 409, 'Only an approved migration batch can be applied.');
            abort_if(in_array($actor->id, [$locked->submitted_by, $locked->reviewed_by], true), 403, 'A third independent operator must apply the approved migration.');

            if (in_array($locked->dataset_type, ['counties', 'organizations', 'sectors', 'programmes', 'programme_county_coverages', 'users', 'sub_counties', 'wards'], true)) {
                return $this->applyReferenceData($locked, $actor);
            }
            if ($locked->dataset_type === 'acpa_reconstruction') {
                return $this->applyLegacyAcpa($locked, $actor);
            }

            $rows = DataMigrationRow::query()
                ->where('data_migration_batch_id', $locked->id)
                ->where('validation_status', 'valid')
                ->with('county')
                ->orderBy('row_number')
                ->lockForUpdate()
                ->get();
            abort_unless($rows->count() === $locked->valid_rows && $locked->invalid_rows === 0, 409, 'The approved row reconciliation no longer matches the staged batch.');

            $naturalKeys = $rows->map(fn (DataMigrationRow $row): string => implode('|', [$locked->dataset_type, $row->county_id, $row->period?->toDateString(), Str::upper((string) $row->metric_code)]))->sort()->values();
            foreach ($naturalKeys as $naturalKey) {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$naturalKey]);
            }
            $hasAppliedConflict = HistoricalMetric::query()
                ->where('dataset_type', $locked->dataset_type)
                ->where(function (Builder $query) use ($rows): void {
                    foreach ($rows as $row) {
                        $query->orWhere(fn (Builder $query) => $query
                            ->where('county_id', $row->county_id)
                            ->whereDate('period', $row->period)
                            ->whereRaw('upper(metric_code) = ?', [Str::upper((string) $row->metric_code)]));
                    }
                })
                ->exists();
            abort_if($hasAppliedConflict, 409, 'Applied history already contains one or more selected county, period and metric keys. Restage the source and resolve the reconciliation exceptions.');

            $importedAt = now();
            foreach ($rows as $row) {
                abort_unless($row->county_id !== null && $row->period !== null && $row->metric_code !== null && $row->metric_name !== null, 409, 'A staged row is missing required reconciled values.');
                $record = [
                    'data_migration_batch_id' => $locked->id,
                    'data_migration_row_id' => $row->id,
                    'county_id' => $row->county_id,
                    'dataset_type' => $locked->dataset_type,
                    'period' => $row->period->toDateString(),
                    'metric_code' => $row->metric_code,
                    'metric_name' => $row->metric_name,
                    'numeric_value' => $row->numeric_value,
                    'narrative_value' => $row->narrative_value,
                    'unit' => $row->unit,
                    'source_name' => $locked->source_name,
                    'source_reference' => $row->source_reference ?? $locked->source_reference,
                    'source_checksum' => $row->source_checksum,
                    'imported_by' => $actor->id,
                    'imported_at' => $importedAt,
                ];
                $checksumPayload = [...$record, 'imported_at' => $importedAt->toIso8601String()];
                $record['record_checksum'] = hash('sha256', json_encode($checksumPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                HistoricalMetric::create($record);
                $row->update(['validation_status' => 'applied', 'applied_at' => $importedAt]);
            }

            $locked->update(['status' => 'applied', 'applied_by' => $actor->id, 'applied_at' => $importedAt]);
            $this->auditLogger->record($actor, $locked, 'data_migration.applied', "Historical data migration applied with {$rows->count()} immutable records.", null, ['file_checksum' => $locked->file_checksum, 'records' => $rows->count()]);

            return $locked->refresh();
        });
    }

    private function applyLegacyAcpa(DataMigrationBatch $batch, User $actor): DataMigrationBatch
    {
        $rows = DataMigrationRow::query()->where('data_migration_batch_id', $batch->id)->where('validation_status', 'valid')->orderBy('row_number')->lockForUpdate()->get();
        abort_unless($rows->count() === $batch->valid_rows && $batch->invalid_rows === 0, 409, 'The approved legacy ACPA reconciliation no longer matches the staged batch.');

        $lockKeys = $rows->map(function (DataMigrationRow $row): string {
            $payload = $row->source_payload ?? [];

            return 'legacy-acpa:'.$row->county_id.':'.Str::upper((string) ($payload['assessment_reference'] ?? ''));
        })->unique()->sort()->values();
        foreach ($lockKeys as $lockKey) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$lockKey]);
        }

        $importedAt = now();
        $assessments = [];
        foreach ($rows->filter(fn (DataMigrationRow $row): bool => ($row->source_payload['record_type'] ?? null) === 'assessment') as $row) {
            $payload = $row->source_payload ?? [];
            abort_unless($row->county_id !== null && $row->period !== null, 409, 'A legacy ACPA assessment header lost its reconciled county or period.');
            $conflict = LegacyAcpaAssessment::query()->where('county_id', $row->county_id)->whereRaw('upper(assessment_reference) = ?', [Str::upper((string) $payload['assessment_reference'])])->exists();
            abort_if($conflict, 409, 'A legacy ACPA assessment reference now exists. Restage and reconcile the source.');
            $record = [
                'data_migration_batch_id' => $batch->id,
                'data_migration_row_id' => $row->id,
                'county_id' => $row->county_id,
                'assessment_reference' => $payload['assessment_reference'],
                'period' => $row->period->toDateString(),
                'cycle_name' => $payload['title'],
                'status' => $payload['status'],
                'overall_score' => $payload['numeric_value'] !== '' ? $payload['numeric_value'] : null,
                'source_name' => $batch->source_name,
                'source_reference' => $payload['source_reference'] ?: $batch->source_reference,
                'source_checksum' => $row->source_checksum,
                'imported_by' => $actor->id,
                'imported_at' => $importedAt,
            ];
            $record['record_checksum'] = $this->checksum($record, $importedAt);
            $assessment = LegacyAcpaAssessment::create($record);
            $assessments[$row->county_id.'|'.Str::upper($payload['assessment_reference'])] = $assessment;
            $row->update(['validation_status' => 'applied', 'applied_at' => $importedAt]);
        }

        foreach ($rows->reject(fn (DataMigrationRow $row): bool => ($row->source_payload['record_type'] ?? null) === 'assessment') as $row) {
            $payload = $row->source_payload ?? [];
            $assessmentKey = $row->county_id.'|'.Str::upper((string) $payload['assessment_reference']);
            $assessment = $assessments[$assessmentKey] ?? LegacyAcpaAssessment::query()->where('county_id', $row->county_id)->whereRaw('upper(assessment_reference) = ?', [Str::upper((string) $payload['assessment_reference'])])->first();
            abort_unless($assessment instanceof LegacyAcpaAssessment, 409, 'The referenced legacy ACPA assessment header is unavailable.');
            $conflict = LegacyAcpaComponent::query()->where('legacy_acpa_assessment_id', $assessment->id)->where('record_type', $payload['record_type'])->whereRaw('upper(record_reference) = ?', [Str::upper((string) $payload['record_reference'])])->exists();
            abort_if($conflict, 409, 'A legacy ACPA component now exists. Restage and reconcile the source.');
            $record = [
                'legacy_acpa_assessment_id' => $assessment->id,
                'data_migration_batch_id' => $batch->id,
                'data_migration_row_id' => $row->id,
                'record_type' => $payload['record_type'],
                'record_reference' => $payload['record_reference'],
                'criterion_code' => $payload['criterion_code'] ?: null,
                'title' => $payload['title'] ?: null,
                'numeric_value' => $payload['numeric_value'] !== '' ? $payload['numeric_value'] : null,
                'maximum_value' => $payload['maximum_value'] !== '' ? $payload['maximum_value'] : null,
                'status' => $payload['status'] ?: null,
                'assignment_role' => $payload['assignment_role'] ?: null,
                'person_identifier' => $payload['person_identifier'] ?: null,
                'person_name' => $payload['person_name'] ?: null,
                'description' => $payload['description'] ?: null,
                'decision' => $payload['decision'] ?: null,
                'file_name' => $payload['file_name'] ?: null,
                'mime_type' => $payload['mime_type'] ?: null,
                'file_checksum' => $payload['file_checksum'] ?: null,
                'source_reference' => $payload['source_reference'] ?: $batch->source_reference,
                'source_payload' => $payload,
                'source_checksum' => $row->source_checksum,
                'imported_by' => $actor->id,
                'imported_at' => $importedAt,
            ];
            $record['record_checksum'] = $this->checksum($record, $importedAt);
            LegacyAcpaComponent::create($record);
            $row->update(['validation_status' => 'applied', 'applied_at' => $importedAt]);
        }

        $batch->update(['status' => 'applied', 'applied_by' => $actor->id, 'applied_at' => $importedAt]);
        $this->auditLogger->record($actor, $batch, 'data_migration.legacy_acpa_applied', "Legacy ACPA reconstruction applied with {$rows->count()} immutable records.", metadata: ['file_checksum' => $batch->file_checksum, 'records' => $rows->count(), 'assessments' => count($assessments)]);

        return $batch->refresh();
    }

    /** @param array<string, mixed> $record */
    private function checksum(array $record, CarbonInterface $importedAt): string
    {
        $payload = [...$record, 'imported_at' => $importedAt->toIso8601String()];
        if (isset($payload['source_payload'])) {
            $payload['source_payload'] = hash('sha256', json_encode($payload['source_payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function applyReferenceData(DataMigrationBatch $batch, User $actor): DataMigrationBatch
    {
        $rows = DataMigrationRow::query()
            ->where('data_migration_batch_id', $batch->id)
            ->where('validation_status', 'valid')
            ->orderBy('row_number')
            ->lockForUpdate()
            ->get();
        abort_unless($rows->count() === $batch->valid_rows && $batch->invalid_rows === 0, 409, 'The approved row validation no longer matches the staged batch.');

        $codes = $rows->pluck('source_payload')->map(fn (?array $payload): string => Str::upper((string) ($payload['code'] ?? '')))->all();
        $emails = $rows->pluck('source_payload')->map(fn (?array $payload): string => Str::lower((string) ($payload['email'] ?? '')))->all();
        $hasConflict = match ($batch->dataset_type) {
            'counties' => County::query()->withTrashed()->whereIn('code', array_map('intval', $codes))->exists(),
            'organizations' => Organization::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'sectors' => Sector::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'programmes' => Programme::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'sub_counties' => SubCounty::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'wards' => Ward::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'programme_county_coverages' => false,
            'users' => User::query()->withTrashed()->whereIn('email', $emails)->exists(),
            default => true,
        };
        abort_if($hasConflict, 409, 'One or more codes now exist. Restage the file against current reference data.');

        if ($batch->dataset_type === 'programme_county_coverages') {
            $lockKeys = $rows->map(function (DataMigrationRow $row): string {
                $payload = $row->source_payload ?? [];
                $programmeId = Programme::query()->whereRaw('upper(code) = ?', [Str::upper((string) ($payload['programme_code'] ?? ''))])->value('id');
                $countyId = County::query()->where('code', (int) ($payload['county_code'] ?? 0))->value('id');
                abort_unless(is_string($programmeId) && is_string($countyId), 409, 'A programme or county reference changed after review. Restage the source.');

                return "programme-coverage:{$programmeId}:{$countyId}";
            })->unique()->sort()->values();
            foreach ($lockKeys as $lockKey) {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$lockKey]);
            }
        }

        $importedAt = now();
        foreach ($rows as $row) {
            $payload = $row->source_payload ?? [];
            match ($batch->dataset_type) {
                'counties' => County::create([
                    'code' => (int) $payload['code'],
                    'name' => $payload['name'],
                    'slug' => Str::slug($payload['name']),
                    'region' => $payload['region'] ?: null,
                    'official_website_url' => $payload['official_website_url'] ?: null,
                    'map_x' => (float) $payload['map_x'],
                    'map_y' => (float) $payload['map_y'],
                ]),
                'organizations' => Organization::create([
                    'code' => $payload['code'],
                    'name' => $payload['name'],
                    'type' => $payload['type'],
                    'county_id' => filled($payload['county_code'] ?? null) ? County::query()->where('code', (int) $payload['county_code'])->value('id') : null,
                    'email' => $payload['email'] ?: null,
                    'status' => $payload['status'],
                    'metadata' => ['import_batch_reference' => $batch->reference, 'source_reference' => $batch->source_reference],
                ]),
                'sectors' => Sector::create([
                    'code' => $payload['code'],
                    'name' => $payload['name'],
                    'parent_sector_id' => filled($payload['parent_sector_code'] ?? null) ? Sector::query()->whereRaw('upper(code) = ?', [Str::upper($payload['parent_sector_code'])])->value('id') : null,
                    'description' => $payload['description'] ?: null,
                    'is_active' => in_array(Str::lower($payload['is_active']), ['1', 'true', 'yes'], true),
                ]),
                'programmes' => Programme::create([
                    'code' => $payload['code'],
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?: null,
                    'lead_organization_id' => filled($payload['lead_organization_code'] ?? null) ? Organization::query()->whereRaw('upper(code) = ?', [Str::upper($payload['lead_organization_code'])])->value('id') : null,
                    'sector_id' => filled($payload['sector_code'] ?? null) ? Sector::query()->whereRaw('upper(code) = ?', [Str::upper($payload['sector_code'])])->value('id') : null,
                    'starts_on' => $payload['starts_on'] ?: null,
                    'ends_on' => $payload['ends_on'] ?: null,
                    'status' => $payload['status'],
                    'budget_amount' => $payload['budget_amount'] ?: null,
                    'currency' => Str::upper($payload['currency']),
                ]),
                'programme_county_coverages' => $this->createImportedProgrammeCountyCoverage($payload, $actor),
                'users' => $this->createImportedUser($payload, $actor),
                'sub_counties' => SubCounty::create([
                    'county_id' => County::query()->where('code', (int) $payload['county_code'])->value('id'),
                    'code' => Str::upper($payload['code']),
                    'name' => $payload['name'],
                    'slug' => Str::slug($payload['name']),
                    'source_authority' => $batch->source_name,
                    'source_reference' => $batch->source_reference,
                    'source_checksum_sha256' => Str::lower($payload['source_checksum_sha256']),
                    'boundary_geojson' => filled($payload['boundary_geojson']) ? json_decode($payload['boundary_geojson'], true, flags: JSON_THROW_ON_ERROR) : null,
                    'boundary_checksum_sha256' => filled($payload['boundary_checksum_sha256']) ? Str::lower($payload['boundary_checksum_sha256']) : null,
                    'effective_from' => $payload['effective_from'],
                    'effective_to' => $payload['effective_to'] ?: null,
                ]),
                'wards' => Ward::create([
                    'sub_county_id' => SubCounty::query()->whereRaw('upper(code) = ?', [Str::upper($payload['sub_county_code'])])->value('id'),
                    'code' => Str::upper($payload['code']),
                    'name' => $payload['name'],
                    'slug' => Str::slug($payload['name']),
                    'source_authority' => $batch->source_name,
                    'source_reference' => $batch->source_reference,
                    'source_checksum_sha256' => Str::lower($payload['source_checksum_sha256']),
                    'boundary_geojson' => filled($payload['boundary_geojson']) ? json_decode($payload['boundary_geojson'], true, flags: JSON_THROW_ON_ERROR) : null,
                    'boundary_checksum_sha256' => filled($payload['boundary_checksum_sha256']) ? Str::lower($payload['boundary_checksum_sha256']) : null,
                    'effective_from' => $payload['effective_from'],
                    'effective_to' => $payload['effective_to'] ?: null,
                ]),
                default => abort(409, 'The approved bulk-import dataset is not supported.'),
            };
            $row->update(['validation_status' => 'applied', 'applied_at' => $importedAt]);
        }

        $release = null;
        if (in_array($batch->dataset_type, ['counties', 'organizations', 'sectors', 'programmes', 'programme_county_coverages', 'sub_counties', 'wards'], true)) {
            $release = $this->createReferenceDataRelease->handle(
                $actor,
                "Automated candidate from governed {$batch->dataset_type} import {$batch->reference}; source {$batch->source_reference}; file SHA-256 {$batch->file_checksum}; {$rows->count()} records. Independent publication required.",
                [
                    'data_migration_batch_id' => $batch->id,
                    'data_migration_batch_reference' => $batch->reference,
                    'source_reference' => $batch->source_reference,
                    'source_file_checksum' => $batch->file_checksum,
                    'records' => $rows->count(),
                ],
            );
        }

        $validationReport = $batch->validation_report ?? [];
        if ($release !== null) {
            $validationReport['reference_data_release'] = [
                'id' => $release->id,
                'version' => $release->version,
                'status' => $release->status,
                'checksum' => $release->checksum,
            ];
        }
        $batch->update([
            'status' => 'applied',
            'applied_by' => $actor->id,
            'applied_at' => $importedAt,
            'validation_report' => $validationReport,
        ]);
        $this->auditLogger->record($actor, $batch, 'data_import.applied', "Bulk {$batch->dataset_type} import atomically applied with {$rows->count()} records.", metadata: [
            'file_checksum' => $batch->file_checksum,
            'records' => $rows->count(),
            'reference_data_release_id' => $release?->id,
            'reference_data_release_version' => $release?->version,
            'reference_data_release_checksum' => $release?->checksum,
        ]);

        return $batch->refresh();
    }

    /** @param array<string, string> $payload */
    private function createImportedProgrammeCountyCoverage(array $payload, User $actor): ProgrammeCountyCoverage
    {
        $programme = Programme::query()->whereRaw('upper(code) = ?', [Str::upper($payload['programme_code'])])->first();
        $county = County::query()->where('code', (int) $payload['county_code'])->first();
        $implementationLeadId = $payload['implementation_lead_code'] !== ''
            ? Organization::query()->where('status', 'active')->whereRaw('upper(code) = ?', [Str::upper($payload['implementation_lead_code'])])->value('id')
            : null;
        abort_unless($programme !== null && $county !== null, 409, 'A programme or county reference changed after review. Restage the source.');
        abort_if($payload['implementation_lead_code'] !== '' && ! is_string($implementationLeadId), 409, 'An implementation-lead reference changed after review. Restage the source.');

        return $this->createProgrammeCountyCoverage->handle($actor, [
            'programme_id' => $programme->id,
            'county_id' => $county->id,
            'implementation_lead_id' => $implementationLeadId,
            'starts_on' => $payload['starts_on'],
            'ends_on' => $payload['ends_on'] ?: null,
            'status' => $payload['status'],
            'funding_allocation' => $payload['funding_allocation'] !== '' ? $payload['funding_allocation'] : null,
            'currency' => Str::upper($payload['currency']),
            'source_reference' => $payload['source_reference'],
            'notes' => $payload['notes'] ?: null,
        ]);
    }

    /** @param array<string, string> $payload */
    private function createImportedUser(array $payload, User $actor): User
    {
        $resolvedHomeCountyId = filled($payload['home_county_code'] ?? null)
            ? County::query()->where('code', (int) $payload['home_county_code'])->value('id')
            : null;
        $homeCountyId = is_string($resolvedHomeCountyId) ? $resolvedHomeCountyId : null;
        /** @var list<string> $assignedCountyIds */
        $assignedCountyIds = collect(preg_split('/[|;,]/', $payload['assigned_county_codes'] ?? '') ?: [])
            ->map(fn (string $code): int => (int) trim($code))
            ->filter()
            ->unique()
            ->pipe(fn ($codes): array => County::query()->whereIn('code', $codes)->pluck('id')->map(fn (mixed $id): string => (string) $id)->values()->all());
        $user = $this->grantProgrammeAccess->handle([
            'name' => $payload['name'],
            'email' => Str::lower($payload['email']),
            'role' => $payload['role'],
            'county_id' => $homeCountyId,
            'assigned_county_ids' => $assignedCountyIds,
        ], $actor, false);
        DB::afterCommit(fn () => $this->grantProgrammeAccess->sendAccessSetup($user));

        return $user;
    }
}
