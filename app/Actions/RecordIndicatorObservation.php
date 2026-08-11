<?php

namespace App\Actions;

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
        $indicator = IndicatorDefinition::query()->find($attributes['indicator_definition_id']);
        $county = County::query()->find($attributes['county_id']);
        abort_unless($indicator instanceof IndicatorDefinition && $county instanceof County, 404);
        abort_unless($actor->canAccessCounty($county), 403);

        if (! $indicator->isCurrentApprovedVersion()) {
            throw ValidationException::withMessages(['indicator_definition_id' => 'Only the current approved indicator version can receive new observations.']);
        }

        $isNarrative = $indicator->value_type === 'text';
        if (($isNarrative && blank($attributes['narrative_value'] ?? null)) || (! $isNarrative && ! isset($attributes['numeric_value']))) {
            throw ValidationException::withMessages([$isNarrative ? 'narrative_value' : 'numeric_value' => 'A value matching the indicator value type is required.']);
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

        $this->auditLogger->record($actor, $observation, 'indicator.observation.submitted', "{$indicator->code} {$attributes['measure_type']} observation submitted.", $county->id, ['provenance' => $attributes['provenance']]);

        return $observation;
    }
}
