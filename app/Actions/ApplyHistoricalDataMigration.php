<?php

namespace App\Actions;

use App\Models\County;
use App\Models\DataMigrationBatch;
use App\Models\DataMigrationRow;
use App\Models\HistoricalMetric;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\Sector;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyHistoricalDataMigration
{
    public function __construct(
        private AuditLogger $auditLogger,
        private GrantProgrammeAccess $grantProgrammeAccess,
        private CreateReferenceDataRelease $createReferenceDataRelease,
    ) {}

    public function handle(DataMigrationBatch $batch, User $actor): DataMigrationBatch
    {
        return DB::transaction(function () use ($batch, $actor): DataMigrationBatch {
            $locked = DataMigrationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($locked->status === 'approved', 409, 'Only an approved migration batch can be applied.');
            abort_if(in_array($actor->id, [$locked->submitted_by, $locked->reviewed_by], true), 403, 'A third independent operator must apply the approved migration.');

            if (in_array($locked->dataset_type, ['organizations', 'sectors', 'programmes', 'users'], true)) {
                return $this->applyReferenceData($locked, $actor);
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
            'organizations' => Organization::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'sectors' => Sector::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'programmes' => Programme::query()->withTrashed()->whereIn('code', $codes)->exists(),
            'users' => User::query()->withTrashed()->whereIn('email', $emails)->exists(),
            default => true,
        };
        abort_if($hasConflict, 409, 'One or more codes now exist. Restage the file against current reference data.');

        $importedAt = now();
        foreach ($rows as $row) {
            $payload = $row->source_payload ?? [];
            match ($batch->dataset_type) {
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
                'users' => $this->createImportedUser($payload, $actor),
                default => abort(409, 'The approved bulk-import dataset is not supported.'),
            };
            $row->update(['validation_status' => 'applied', 'applied_at' => $importedAt]);
        }

        $release = null;
        if (in_array($batch->dataset_type, ['organizations', 'sectors', 'programmes'], true)) {
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
