<?php

namespace App\Actions;

use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateEvaluationFindingAction
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array{accountable_owner_id: string, code: string, title: string, description: string, success_indicator: string, target: string, due_at: string, weight_percentage: float|int|string} $attributes */
    public function handle(EvaluationFinding $finding, User $actor, array $attributes): EvaluationFindingAction
    {
        abort_unless($finding->status === 'open', 409, __('evaluation-findings.errors.action_requires_open_finding'));
        abort_unless($finding->accountable_owner_id === $actor->id || $actor->programmeRole()->hasNationalScope(), 403);
        abort_if(CarbonImmutable::parse($attributes['due_at'])->startOfDay()->greaterThan($finding->due_at), 422, __('evaluation-findings.errors.action_due_after_finding'));
        $owner = User::query()->whereKey($attributes['accountable_owner_id'])->firstOrFail();
        abort_unless($finding->county_id !== null && $owner->county_id === $finding->county_id && $owner->access_revoked_at === null, 422, __('evaluation-findings.errors.action_owner_scope'));
        $weight = (float) $attributes['weight_percentage'];
        $snapshot = [...$attributes, 'finding_id' => $finding->id, 'created_by' => $actor->id];

        $action = DB::transaction(function () use ($finding, $actor, $attributes, $weight, $snapshot): EvaluationFindingAction {
            $locked = EvaluationFinding::query()->lockForUpdate()->findOrFail($finding->id);
            abort_unless($locked->status === 'open', 409, __('evaluation-findings.errors.action_requires_open_finding'));
            $allocated = (float) $locked->actions()->sum('weight_percentage');
            if (($allocated + $weight) > 100) {
                throw ValidationException::withMessages(['weight_percentage' => __('evaluation-findings.errors.action_weight_limit')]);
            }

            $action = $locked->actions()->create([...$attributes, 'created_by' => $actor->id, 'checksum' => $this->canonicalJson->checksum($snapshot)]);
            $weightedProgress = (float) $locked->actions()->sum(DB::raw('progress_percentage * weight_percentage / 100'));
            $locked->update(['progress_percentage' => round($weightedProgress, 2)]);

            return $action;
        });
        $this->auditLogger->record($actor, $action, 'evaluation.finding_action.created', __('evaluation-findings.audit.action_created', ['action' => $action->code, 'reference' => $finding->reference]), $finding->county_id, ['weight_percentage' => $weight, 'checksum' => $action->checksum]);

        return $action;
    }
}
