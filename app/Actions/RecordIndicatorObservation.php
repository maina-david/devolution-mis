<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorObservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class RecordIndicatorObservation
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): IndicatorObservation
    {
        abort_unless($actor->can(ProgrammePermission::SubmitIndicatorData->value), 403, __('monitoring-results.observation.errors.submit_unauthorized'));

        $indicator = IndicatorDefinition::query()->find($attributes['indicator_definition_id']);
        $county = County::query()->find($attributes['county_id']);
        abort_unless($indicator instanceof IndicatorDefinition && $county instanceof County, 404);
        abort_unless($actor->canAccessCounty($county), 403, __('monitoring-results.observation.errors.county_scope'));

        if (! $indicator->isCurrentApprovedVersion()) {
            throw ValidationException::withMessages(['indicator_definition_id' => __('monitoring-results.observation.errors.current_approved_indicator_required')]);
        }

        $isNarrative = $indicator->value_type === 'text';
        if (($isNarrative && blank($attributes['narrative_value'] ?? null)) || (! $isNarrative && ! isset($attributes['numeric_value']))) {
            throw ValidationException::withMessages([$isNarrative ? 'narrative_value' : 'numeric_value' => __('monitoring-results.observation.errors.value_type_required')]);
        }

        $identity = Arr::only($attributes, ['indicator_definition_id', 'county_id', 'programme_id', 'period_start', 'period_end', 'measure_type']);
        $identity['dimension_key'] = $attributes['dimension_key'] ?? 'total';
        $observation = IndicatorObservation::query()->updateOrCreate($identity, [
            ...Arr::except($attributes, array_keys($identity)),
            'verification_status' => 'submitted',
            'quality_status' => 'unassessed',
            'quality_issues' => null,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
        ]);

        $this->auditLogger->record($actor, $observation, 'indicator.observation.submitted', __('monitoring-results.observation.audit.submitted', ['code' => $indicator->code, 'measure' => $attributes['measure_type']]), $county->id, ['provenance' => $attributes['provenance']]);

        return $observation;
    }
}
