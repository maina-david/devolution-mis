<?php

namespace App\Actions;

use App\Models\ReferenceLineageDisposition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LegacyReferenceInventory;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class ApplyReferenceLineageDisposition
{
    public function __construct(
        private AuditLogger $auditLogger,
        private CanonicalJson $canonicalJson,
        private LegacyReferenceInventory $inventory,
    ) {}

    public function handle(ReferenceLineageDisposition $disposition, User $actor): ReferenceLineageDisposition
    {
        return DB::transaction(function () use ($actor, $disposition): ReferenceLineageDisposition {
            $locked = ReferenceLineageDisposition::query()->with('referenceDataRelease')->lockForUpdate()->findOrFail($disposition->id);
            abort_unless($locked->status === 'approved', 409, 'Only an approved lineage disposition can be applied.');
            abort_if(in_array($actor->id, [$locked->proposed_by, $locked->reviewed_by], true), 403, 'A third independent operator must apply the approved disposition.');
            $record = $this->inventory->record($locked->record_type, $locked->record_id, $locked->decision === 'pin_release');
            abort_unless($this->canonicalJson->checksum($record->getAttributes()) === $locked->record_checksum, 409, 'The source record changed after proposal. Create a new reconciliation decision from its current state.');
            $decisionPayload = [
                'record_type' => $locked->record_type,
                'record_id' => $locked->record_id,
                'decision' => $locked->decision,
                'reference_data_release_id' => $locked->reference_data_release_id,
                'reference_data_release_checksum' => $locked->referenceDataRelease?->checksum,
                'successor_record_type' => $locked->successor_record_type,
                'successor_record_id' => $locked->successor_record_id,
                'record_checksum' => $locked->record_checksum,
                'business_reason' => $locked->business_reason,
                'source_reference' => $locked->source_reference,
                'proposed_by' => $locked->proposed_by,
            ];
            abort_unless($this->canonicalJson->checksum($decisionPayload) === $locked->decision_checksum, 409, 'The approved lineage decision failed checksum verification.');

            if ($locked->successor_record_type !== null && $locked->successor_record_id !== null) {
                $this->inventory->record($locked->successor_record_type, $locked->successor_record_id);
            }
            if ($locked->decision === 'pin_release') {
                $release = $locked->referenceDataRelease;
                abort_unless($release !== null && $release->status === 'published' && $release->effective_from?->isPast(), 409, 'The approved reference-data release is no longer effective.');
                abort_unless($this->canonicalJson->checksum($release->snapshot) === $release->checksum, 409, 'The approved reference-data release failed checksum verification.');
                $record->update([$this->inventory->releaseColumn($locked->record_type) => $release->id]);
            }

            $locked->update(['status' => 'applied', 'applied_by' => $actor->id, 'applied_at' => now()]);
            $this->auditLogger->record($actor, $locked, 'reference_lineage.applied', "Reference lineage disposition {$locked->reference} applied.", metadata: ['record_type' => $locked->record_type, 'record_id' => $locked->record_id, 'decision' => $locked->decision, 'reference_data_release_id' => $locked->reference_data_release_id, 'successor_record_type' => $locked->successor_record_type, 'successor_record_id' => $locked->successor_record_id, 'record_checksum' => $locked->record_checksum, 'decision_checksum' => $locked->decision_checksum]);

            return $locked->refresh();
        });
    }
}
