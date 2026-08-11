<?php

namespace App\Actions;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentCorrectivePlan;
use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAssessmentCorrectivePlan
{
    public function __construct(private AuditLogger $auditLogger, private CanonicalJson $canonicalJson) {}

    /** @param array{assessment_finding_id?: string|null, assessment_appeal_id?: string|null, reference: string, title: string, root_cause: string, expected_outcome: string, due_at: string, actions: list<array{accountable_owner_id: string, code: string, title: string, description: string, success_indicator: string, target: string, due_at: string}>} $data */
    public function handle(Assessment $assessment, User $actor, array $data): AssessmentCorrectivePlan
    {
        abort_unless($actor->canAccessCounty($assessment->county), 403);
        abort_unless($assessment->status === AssessmentStatus::Published && $assessment->publication()->exists(), 409, 'Corrective plans require an immutable published assessment result.');
        $findingId = $data['assessment_finding_id'] ?? null;
        $appealId = $data['assessment_appeal_id'] ?? null;
        if (($findingId === null) === ($appealId === null)) {
            throw ValidationException::withMessages(['source' => 'Select exactly one resolved finding or accepted appeal.']);
        }
        if ($findingId !== null) {
            $finding = AssessmentFinding::query()->findOrFail($findingId);
            abort_unless($finding->assessment_id === $assessment->id, 404);
            abort_unless($finding->status === 'resolved' && in_array($finding->severity, ['major', 'critical'], true), 409, 'Only resolved major or critical findings can initiate corrective follow-through.');
        }
        if ($appealId !== null) {
            $appeal = AssessmentAppeal::query()->findOrFail($appealId);
            abort_unless($appeal->assessment_id === $assessment->id, 404);
            abort_unless($appeal->status === 'accepted', 409, 'Only accepted appeals can initiate corrective follow-through.');
        }
        $planDueAt = CarbonImmutable::parse($data['due_at'])->startOfDay();
        foreach ($data['actions'] as $index => $action) {
            if (CarbonImmutable::parse($action['due_at'])->startOfDay()->greaterThan($planDueAt)) {
                throw ValidationException::withMessages(["actions.{$index}.due_at" => 'An action cannot be due after the corrective plan.']);
            }
        }
        $snapshot = ['assessment_id' => $assessment->id, 'county_id' => $assessment->county_id, 'source' => ['finding_id' => $findingId, 'appeal_id' => $appealId], 'reference' => $data['reference'], 'title' => $data['title'], 'root_cause' => $data['root_cause'], 'expected_outcome' => $data['expected_outcome'], 'due_at' => $data['due_at'], 'actions' => $data['actions']];

        $plan = DB::transaction(function () use ($assessment, $actor, $data, $findingId, $appealId, $snapshot): AssessmentCorrectivePlan {
            $plan = $assessment->correctivePlans()->create([
                'county_id' => $assessment->county_id,
                'assessment_finding_id' => $findingId,
                'assessment_appeal_id' => $appealId,
                'submitted_by' => $actor->id,
                'reference' => $data['reference'],
                'title' => $data['title'],
                'root_cause' => $data['root_cause'],
                'expected_outcome' => $data['expected_outcome'],
                'status' => 'submitted',
                'due_at' => $data['due_at'],
                'submitted_at' => now(),
                'checksum' => $this->canonicalJson->checksum($snapshot),
            ]);
            $plan->actions()->createMany($data['actions']);

            return $plan->load('actions');
        });
        $this->auditLogger->record($actor, $plan, 'assessment.corrective_plan_submitted', "Corrective plan {$plan->reference} submitted.", $assessment->county_id, ['checksum' => $plan->checksum, 'action_count' => $plan->actions->count()]);

        return $plan;
    }
}
