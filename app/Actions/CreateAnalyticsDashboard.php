<?php

namespace App\Actions;

use App\Models\AnalyticsDashboard;
use App\Models\County;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAnalyticsDashboard
{
    public function __construct(
        private AuditLogger $auditLogger,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): AnalyticsDashboard
    {
        return DB::transaction(function () use ($actor, $attributes): AnalyticsDashboard {
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $county = $countyId !== null ? County::query()->find($countyId) : null;
            if ($countyId !== null && (! $county instanceof County || ! $actor->canAccessCounty($county))) {
                throw ValidationException::withMessages(['county_id' => 'The selected county is outside your authorized scope.']);
            }
            $referenceDataRelease = $this->referenceDataReleaseResolver->forAnalyticsDashboard($countyId, now());
            $widgets = is_array($attributes['widgets']) ? $attributes['widgets'] : [];
            unset($attributes['widgets']);
            $dashboard = AnalyticsDashboard::create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id, 'created_by' => $actor->id, 'status' => 'draft']);
            foreach ($widgets as $widget) {
                if (is_array($widget)) {
                    $dashboard->widgets()->create($widget);
                }
            }
            $this->auditLogger->record($actor, $dashboard, 'analytics.dashboard.created', "Analytics dashboard {$dashboard->code} created as a draft.", $dashboard->county_id, ['reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $dashboard;
        });
    }
}
