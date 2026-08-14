<?php

namespace App\Actions;

use App\Models\ReferenceDataRelease;
use App\Models\ReferenceLineageDisposition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LegacyReferenceInventory;
use App\Support\CanonicalJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateReferenceLineageDisposition
{
    public function __construct(
        private AuditLogger $auditLogger,
        private CanonicalJson $canonicalJson,
        private LegacyReferenceInventory $inventory,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): ReferenceLineageDisposition
    {
        return DB::transaction(function () use ($actor, $attributes): ReferenceLineageDisposition {
            $recordType = (string) $attributes['record_type'];
            $recordId = (string) $attributes['record_id'];
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["reference-lineage:{$recordType}:{$recordId}"]);
            $record = $this->inventory->record($recordType, $recordId, true);
            abort_if(ReferenceLineageDisposition::query()->where('record_type', $recordType)->where('record_id', $recordId)->whereIn('status', ['proposed', 'approved', 'applied'])->exists(), 409, __('migration.lineage_errors.active_disposition'));

            $successorType = filled($attributes['successor_record_type'] ?? null) ? (string) $attributes['successor_record_type'] : null;
            $successorId = filled($attributes['successor_record_id'] ?? null) ? (string) $attributes['successor_record_id'] : null;
            if ($successorType !== null && $successorId !== null) {
                abort_if($successorType === $recordType && $successorId === $recordId, 422, __('migration.lineage_errors.self_successor'));
                $this->inventory->record($successorType, $successorId);
            }

            $release = null;
            if ($attributes['decision'] === 'pin_release') {
                $release = ReferenceDataRelease::query()->where('status', 'published')->where('effective_from', '<=', now())->whereKey((string) $attributes['reference_data_release_id'])->firstOrFail();
                abort_unless($this->canonicalJson->checksum($release->snapshot) === $release->checksum, 409, __('migration.lineage_errors.release_checksum'));
                $this->assertReferencesExistInRelease($record, $release);
            }

            $recordSnapshot = $this->inventory->safeSnapshot($record);
            $recordChecksum = $this->canonicalJson->checksum($record->getAttributes());
            $decisionPayload = [
                'record_type' => $recordType,
                'record_id' => $recordId,
                'decision' => $attributes['decision'],
                'reference_data_release_id' => $release?->id,
                'reference_data_release_checksum' => $release?->checksum,
                'successor_record_type' => $successorType,
                'successor_record_id' => $successorId,
                'record_checksum' => $recordChecksum,
                'business_reason' => $attributes['business_reason'],
                'source_reference' => $attributes['source_reference'],
                'proposed_by' => $actor->id,
            ];
            $disposition = ReferenceLineageDisposition::create([
                ...Arr::except($decisionPayload, ['reference_data_release_checksum']),
                'reference' => 'RLD-'.now()->format('Y').'-'.Str::upper(Str::random(10)),
                'record_snapshot' => $recordSnapshot,
                'status' => 'proposed',
                'decision_checksum' => $this->canonicalJson->checksum($decisionPayload),
            ]);
            $this->auditLogger->record($actor, $disposition, 'reference_lineage.proposed', __('migration.lineage_audit.proposed', ['reference' => $disposition->reference]), metadata: ['record_type' => $recordType, 'record_id' => $recordId, 'decision' => $attributes['decision'], 'record_checksum' => $recordChecksum, 'decision_checksum' => $disposition->decision_checksum]);

            return $disposition;
        });
    }

    private function assertReferencesExistInRelease(Model $record, ReferenceDataRelease $release): void
    {
        $mappings = [
            'county_id' => 'counties', 'target_county_id' => 'counties', 'source_county_id' => 'counties',
            'organization_id' => 'organizations', 'partner_organization_id' => 'organizations', 'owner_organization_id' => 'organizations',
            'accountable_organization_id' => 'organizations', 'lead_organization_id' => 'organizations',
            'sector_id' => 'sectors', 'programme_id' => 'programmes',
        ];
        foreach ($mappings as $column => $catalogue) {
            $id = $record->getAttribute($column);
            if (! is_string($id) || $id === '') {
                continue;
            }
            abort_unless(collect($release->snapshot[$catalogue] ?? [])->contains(fn (array $entry): bool => ($entry['id'] ?? null) === $id), 409, __('migration.lineage_errors.reference_missing', ['column' => $column]));
        }
    }
}
