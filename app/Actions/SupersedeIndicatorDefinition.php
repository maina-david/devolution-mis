<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IndicatorDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupersedeIndicatorDefinition
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, IndicatorDefinition $indicator, array $attributes): IndicatorDefinition
    {
        abort_unless($actor->can(ProgrammePermission::ManageIndicators->value), 403, __('indicator-definitions.errors.manage_unauthorized'));

        return DB::transaction(function () use ($actor, $indicator, $attributes): IndicatorDefinition {
            $current = IndicatorDefinition::query()->lockForUpdate()->findOrFail($indicator->id);

            if (! $current->isCurrentApprovedVersion()) {
                throw ValidationException::withMessages(['indicator' => __('indicator-definitions.errors.current_approved_required')]);
            }

            if (IndicatorDefinition::withTrashed()->where('supersedes_id', $current->id)->exists()) {
                throw ValidationException::withMessages(['indicator' => __('indicator-definitions.errors.successor_exists')]);
            }
            $release = $this->referenceDataReleaseResolver->forIndicatorDefinition($current->sector_id, $current->programme_id, now());

            $successor = IndicatorDefinition::create([
                ...Arr::only($current->getAttributes(), ['code', 'sector_id', 'programme_id', 'disaggregation_dimensions', 'calculation_formula']),
                ...$attributes,
                'supersedes_id' => $current->id,
                'version' => $current->version + 1,
                'status' => 'draft',
                'created_by' => $actor->id,
                'reference_data_release_id' => $release->id,
            ]);

            $this->auditLogger->record($actor, $successor, 'indicator.definition.supersession.drafted', __('indicator-definitions.audit.superseded', ['code' => $current->code, 'successor' => $successor->version, 'prior' => $current->version]), metadata: ['supersedes_id' => $current->id, 'change_summary' => $attributes['change_summary'], 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $successor;
        });
    }
}
