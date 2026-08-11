<?php

namespace App\Actions;

use App\Models\IgrResolution;
use App\Models\IgrResolutionUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class RecordIgrResolutionUpdate
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(IgrResolution $resolution, User $actor, array $attributes): IgrResolutionUpdate
    {
        abort_unless(in_array($resolution->status, ['open', 'in_progress'], true), 409, 'Updates are accepted only while implementation is active.');
        abort_if((int) $attributes['progress_percentage'] < $resolution->progress_percentage, 422, 'Progress cannot move backwards.');

        return DB::transaction(function () use ($resolution, $actor, $attributes): IgrResolutionUpdate {
            $update = $resolution->updates()->create([...$attributes, 'reported_by' => $actor->id, 'reported_at' => now()]);
            $resolution->update(['progress_percentage' => $attributes['progress_percentage'], 'implementation_gap' => $attributes['implementation_gap'] ?? $resolution->implementation_gap, 'closure_evidence' => $attributes['evidence_reference'] ?? $resolution->closure_evidence]);
            $countyId = $resolution->assignments()->whereNotNull('county_id')->value('county_id');
            $this->auditLogger->record($actor, $update, 'igr.resolution.progress_reported', "Progress reported for {$resolution->resolution_number}.", $countyId, ['progress_percentage' => $attributes['progress_percentage']]);

            return $update->refresh();
        });
    }
}
