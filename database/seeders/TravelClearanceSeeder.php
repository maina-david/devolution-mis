<?php

namespace Database\Seeders;

use App\Actions\CreateTravelRequest;
use App\Actions\TransitionTravelRequest;
use App\Models\County;
use App\Models\TravelRequest;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;

class TravelClearanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateTravelRequest $createTravelRequest, TransitionTravelRequest $transitionTravelRequest): void
    {
        if (! app()->isLocal() || TravelRequest::query()->exists()) {
            return;
        }

        $requester = User::query()->where('email', 'county.official@idmis.test')->first();
        $manager = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $finance = User::query()->where('email', 'management@idmis.test')->first();
        $county = County::query()->where('name', 'Mombasa')->first();
        if (! $requester || ! $manager || ! $finance || ! $county) {
            return;
        }

        $departure = today()->addWeeks(3);
        $travelRequest = $createTravelRequest->handle($requester, [
            'county_id' => $county->id,
            'organization_id' => null,
            'sector_id' => null,
            'travel_type' => 'domestic',
            'purpose' => 'County implementation readiness review',
            'justification' => 'Conduct an evidence-based readiness review with county programme, finance and service-delivery teams.',
            'destination_country' => ReferenceCatalogue::defaultCountryName(),
            'destination_county' => 'Nairobi',
            'destination_city' => 'Nairobi',
            'departure_date' => $departure->toDateString(),
            'return_date' => $departure->copy()->addDays(2)->toDateString(),
            'estimated_cost' => 68000,
            'currency' => ReferenceCatalogue::defaultCurrency(),
            'funding_source' => 'KDSP II programme operations',
            'cost_centre' => 'KDSP-OPS-01',
            'hris_employee_reference' => 'IPPD-DEMO-001',
            'priority' => 'normal',
            'itineraries' => [[
                'origin' => 'Mombasa', 'destination' => 'Nairobi', 'departs_at' => $departure->copy()->setTime(8, 0), 'arrives_at' => $departure->copy()->setTime(9, 15), 'transport_mode' => 'air', 'carrier' => 'Approved carrier', 'estimated_cost' => 28000,
            ], [
                'origin' => 'Nairobi', 'destination' => 'Mombasa', 'departs_at' => $departure->copy()->addDays(2)->setTime(17, 0), 'arrives_at' => $departure->copy()->addDays(2)->setTime(18, 15), 'transport_mode' => 'air', 'carrier' => 'Approved carrier', 'estimated_cost' => 28000,
            ]],
        ]);
        $transitionTravelRequest->handle($travelRequest, $requester, ['transition' => 'submit', 'rationale' => 'Complete itinerary and cost estimate submitted for approval.']);
        $transitionTravelRequest->handle($travelRequest->refresh(), $manager, ['transition' => 'manager_approve', 'rationale' => 'Travel is necessary and aligned to the approved work plan.', 'approved_cost' => 68000]);
        $transitionTravelRequest->handle($travelRequest->refresh(), $finance, ['transition' => 'finance_clear', 'rationale' => 'Budget availability and commitment independently confirmed.', 'approved_cost' => 68000, 'finance_commitment_reference' => 'IFMIS-COMMIT-DEMO-001']);
    }
}
