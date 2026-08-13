<?php

namespace App\Actions;

use App\Models\County;
use App\Models\TravelRequest;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateTravelRequest
{
    public function __construct(
        private StartWorkflow $startWorkflow,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): TravelRequest
    {
        if ($attributes['county_id'] ?? null) {
            abort_unless($actor->canAccessCounty(County::query()->whereKey($attributes['county_id'])->firstOrFail()), 403);
        }

        return DB::transaction(function () use ($actor, $attributes): TravelRequest {
            $organizationId = is_string($attributes['organization_id'] ?? null) ? $attributes['organization_id'] : null;
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $sectorId = is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forTravelRequest($organizationId, $countyId, $sectorId, now());
            $itineraries = collect($this->records($attributes['itineraries'] ?? null));
            $request = TravelRequest::create([...collect($attributes)->except('itineraries')->all(), 'reference' => 'TRV-'.now()->format('Y').'-'.mb_strtoupper(Str::random(8)), 'requester_id' => $actor->id, 'created_by' => $actor->id, 'reference_data_release_id' => $referenceDataRelease->id, 'integration_status' => 'pending', 'status' => 'draft']);
            foreach ($itineraries->values() as $sequence => $itinerary) {
                $request->itineraries()->create([...$itinerary, 'sequence' => $sequence + 1]);
            }
            $definition = WorkflowDefinition::query()->where('code', 'TRAVEL-CLEARANCE-LIFECYCLE')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $request, $actor, ['itinerary_count' => $itineraries->count(), 'estimated_cost' => (float) $request->estimated_cost, 'finance_reference_present' => false], $request->county_id);
            $request->update(['workflow_instance_id' => $instance->id]);
            $this->auditLogger->record($actor, $request, 'travel.request.created', __('travel-clearance.audit.created', ['reference' => $request->reference]), $request->county_id, [
                'estimated_cost' => $request->estimated_cost,
                'destination' => $request->destination_city,
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $request->refresh();
        });
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(__('travel-clearance.errors.itineraries_array'));
        }

        return array_values(array_map(function (mixed $itinerary): array {
            if (! is_array($itinerary)) {
                throw new InvalidArgumentException(__('travel-clearance.errors.itinerary_object'));
            }

            return $itinerary;
        }, $value));
    }
}
