<?php

namespace App\Services;

use App\Models\PartnerCollaborationAlert;
use App\Models\PartnerProfile;
use Illuminate\Support\Collection;

class PartnerOverlapAnalyzer
{
    /** @return Collection<int, PartnerCollaborationAlert> */
    public function analyze(): Collection
    {
        $partners = PartnerProfile::query()->where('status', 'active')->with(['organization:id,name', 'counties:id,name', 'sectors:id,name', 'contributions:id,partner_profile_id,devolution_project_id'])->orderBy('id')->get();
        $alerts = collect();

        foreach ($partners as $index => $primary) {
            foreach ($partners->slice($index + 1) as $related) {
                $countyIds = $primary->counties->pluck('id')->intersect($related->counties->pluck('id'))->sort()->values();
                $sectorIds = $primary->sectors->pluck('id')->intersect($related->sectors->pluck('id'))->sort()->values();
                $projectIds = $primary->contributions->pluck('devolution_project_id')->intersect($related->contributions->pluck('devolution_project_id'))->sort()->values();
                if ($countyIds->isEmpty() || $sectorIds->isEmpty()) {
                    continue;
                }
                $alertType = $projectIds->isNotEmpty() ? 'overlap' : 'synergy';
                $scope = ['county_ids' => $countyIds->all(), 'sector_ids' => $sectorIds->all(), 'project_ids' => $projectIds->all()];
                $fingerprint = hash('sha256', json_encode(['partners' => [$primary->id, $related->id], 'type' => $alertType, 'scope' => $scope], JSON_THROW_ON_ERROR));
                $alert = PartnerCollaborationAlert::query()->firstOrCreate(['scope_fingerprint' => $fingerprint], [
                    'primary_partner_id' => $primary->id, 'related_partner_id' => $related->id, 'alert_type' => $alertType,
                    'severity' => $projectIds->isNotEmpty() ? 'high' : 'medium', 'scope' => $scope,
                    'summary' => $projectIds->isNotEmpty()
                        ? "{$primary->organization->name} and {$related->organization->name} report contributions to the same project and geography."
                        : "{$primary->organization->name} and {$related->organization->name} share county and sector coverage with collaboration potential.",
                    'detected_at' => now(),
                ]);
                $alerts->push($alert);
            }
        }

        return $alerts;
    }
}
