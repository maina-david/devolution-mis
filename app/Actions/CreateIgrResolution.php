<?php

namespace App\Actions;

use App\Models\County;
use App\Models\IgrForumMeeting;
use App\Models\IgrResolution;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateIgrResolution
{
    public function __construct(
        private StartWorkflow $startWorkflow,
        private AuditLogger $auditLogger,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): IgrResolution
    {
        if (! empty($attributes['igr_forum_meeting_id'])) {
            $meeting = IgrForumMeeting::query()->whereKey((string) $attributes['igr_forum_meeting_id'])->firstOrFail();
            if ($meeting->igr_forum_id !== $attributes['igr_forum_id']) {
                throw ValidationException::withMessages(['igr_forum_meeting_id' => 'The selected meeting must belong to the resolution forum.']);
            }
            if (! $meeting->quorum_confirmed) {
                throw ValidationException::withMessages(['igr_forum_meeting_id' => 'A resolution can only be linked to a quorum-confirmed meeting.']);
            }
        }
        $normalizedAssignments = $this->normalizeAssignments($attributes['assignments'] ?? []);
        $assignments = collect($normalizedAssignments);
        foreach ($assignments->pluck('county_id')->filter()->unique() as $countyId) {
            $county = County::query()->whereKey($countyId)->firstOrFail();
            abort_unless($actor->canAccessCounty($county), 403);
        }
        if ($assignments->where('is_lead', true)->count() !== 1) {
            throw ValidationException::withMessages(['assignments' => 'Exactly one lead assignment is required.']);
        }
        [$countyIds, $organizationIds] = $this->referenceIds($normalizedAssignments);

        return DB::transaction(function () use ($actor, $attributes, $assignments, $countyIds, $organizationIds): IgrResolution {
            $referenceDataRelease = $this->referenceDataReleaseResolver->forIgrResolution($countyIds, $organizationIds, now());
            $resolution = IgrResolution::create([
                ...collect($attributes)->except('assignments')->all(),
                'created_by' => $actor->id,
                'reference_data_release_id' => $referenceDataRelease->id,
            ]);
            foreach ($assignments as $assignment) {
                $resolution->assignments()->create($assignment);
            }
            $countyId = $assignments->pluck('county_id')->filter()->unique()->count() === 1 ? $assignments->pluck('county_id')->filter()->first() : null;
            $definition = WorkflowDefinition::query()->where('code', 'IGR-RESOLUTION-LIFECYCLE')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $resolution, $actor, ['progress_percentage' => 0, 'closure_evidence_present' => false], $countyId);
            $resolution->update(['workflow_instance_id' => $instance->id, 'status' => $instance->current_state]);
            $this->auditLogger->record($actor, $resolution, 'igr.resolution.created', "IGR resolution {$resolution->resolution_number} registered.", $countyId, [
                'assignment_count' => $assignments->count(),
                'due_on' => $resolution->due_on->toDateString(),
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $resolution->refresh();
        });
    }

    /** @return list<array{user_id?: string|null, organization_id?: string|null, county_id?: string|null, responsibility_role: string, is_lead: bool}> */
    private function normalizeAssignments(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $assignments = [];
        foreach ($value as $assignment) {
            if (! is_array($assignment) || ! isset($assignment['responsibility_role'])) {
                continue;
            }
            $assignments[] = [
                'user_id' => isset($assignment['user_id']) ? (string) $assignment['user_id'] : null,
                'organization_id' => isset($assignment['organization_id']) ? (string) $assignment['organization_id'] : null,
                'county_id' => isset($assignment['county_id']) ? (string) $assignment['county_id'] : null,
                'responsibility_role' => (string) $assignment['responsibility_role'],
                'is_lead' => filter_var($assignment['is_lead'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return $assignments;
    }

    /**
     * @param  list<array{user_id?: string|null, organization_id?: string|null, county_id?: string|null, responsibility_role: string, is_lead: bool}>  $assignments
     * @return array{list<string>, list<string>}
     */
    private function referenceIds(array $assignments): array
    {
        $countyIds = [];
        $organizationIds = [];
        foreach ($assignments as $assignment) {
            if (is_string($assignment['county_id'] ?? null)) {
                $countyIds[] = $assignment['county_id'];
            }
            if (is_string($assignment['organization_id'] ?? null)) {
                $organizationIds[] = $assignment['organization_id'];
            }
        }

        return [array_values(array_unique($countyIds)), array_values(array_unique($organizationIds))];
    }
}
