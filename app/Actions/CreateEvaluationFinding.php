<?php

namespace App\Actions;

use App\Models\EvaluationFinding;
use App\Models\ProgrammeEvaluation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class CreateEvaluationFinding
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ProgrammeEvaluation $evaluation, User $actor, array $attributes): EvaluationFinding
    {
        abort_unless($evaluation->status === 'approved', 409, __('evaluation-findings.errors.approved_evaluation_required'));
        abort_unless($actor->programmeRole()->hasNationalScope() || ($evaluation->county_id !== null && $actor->canAccessCounty($evaluation->county)), 403);
        abort_if($evaluation->approved_by === $actor->id, 409, __('evaluation-findings.errors.approver_issuer_separation'));
        $owner = User::query()->whereKey($attributes['accountable_owner_id'])->firstOrFail();
        abort_unless($evaluation->county_id === null ? $owner->programmeRole()->hasNationalScope() : $owner->canAccessCounty($evaluation->county), 422, __('evaluation-findings.errors.owner_scope'));
        $snapshot = [...$attributes, 'evaluation_id' => $evaluation->id, 'county_id' => $evaluation->county_id, 'created_by' => $actor->id];

        $finding = DB::transaction(fn (): EvaluationFinding => $evaluation->governedFindings()->create([...$attributes, 'county_id' => $evaluation->county_id, 'created_by' => $actor->id, 'checksum' => $this->canonicalJson->checksum($snapshot)]));
        $this->auditLogger->record($actor, $finding, 'evaluation.finding.created', __('evaluation-findings.audit.created', ['reference' => $finding->reference]), $evaluation->county_id, ['checksum' => $finding->checksum]);

        return $finding;
    }
}
