<?php

namespace App\Http\Controllers;

use App\Actions\AttestAssessment;
use App\Actions\CalculateAssessmentScore;
use App\Actions\CreateAssessment;
use App\Actions\CreateAssessmentCorrectivePlan;
use App\Actions\DecideAssessmentAppeal;
use App\Actions\DecideAssessmentCorrectiveClosure;
use App\Actions\OverrideCriterionScore;
use App\Actions\PublishAssessmentResult;
use App\Actions\RecordAssessmentCorrectiveUpdate;
use App\Actions\RecordAssessmentFinding;
use App\Actions\RequestAssessmentCorrectiveClosure;
use App\Actions\ResolveAssessmentFinding;
use App\Actions\RespondToAssessmentFinding;
use App\Actions\ReviewAssessmentCorrectivePlan;
use App\Actions\SubmitAssessmentAppeal;
use App\Actions\SubmitCriterionScore;
use App\Actions\TransitionAssessment;
use App\Actions\VerifyAssessmentCorrectiveUpdate;
use App\Actions\VerifyCriterionScore;
use App\Enums\AssessmentStatus;
use App\Enums\ProgrammePermission;
use App\Http\Requests\AttestAssessmentRequest;
use App\Http\Requests\DecideAssessmentAppealRequest;
use App\Http\Requests\DecideAssessmentCorrectiveClosureRequest;
use App\Http\Requests\OverrideCriterionScoreRequest;
use App\Http\Requests\RequestAssessmentCorrectiveClosureRequest;
use App\Http\Requests\ResolveAssessmentFindingRequest;
use App\Http\Requests\RespondAssessmentFindingRequest;
use App\Http\Requests\ReviewAssessmentCorrectivePlanRequest;
use App\Http\Requests\ScoreAssessmentRequest;
use App\Http\Requests\StoreAssessmentAppealRequest;
use App\Http\Requests\StoreAssessmentCorrectivePlanRequest;
use App\Http\Requests\StoreAssessmentCorrectiveUpdateRequest;
use App\Http\Requests\StoreAssessmentFindingRequest;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\SubmitCriterionScoreRequest;
use App\Http\Requests\VerifyAssessmentCorrectiveUpdateRequest;
use App\Http\Requests\VerifyCriterionScoreRequest;
use App\Models\Assessment;
use App\Models\AssessmentAppeal;
use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectivePlan;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCriterionResult;
use App\Models\AssessmentDocument;
use App\Models\AssessmentFinding;
use App\Models\User;
use App\Services\AssessmentDetailData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentWorkflowController extends Controller
{
    public function __construct(private TransitionAssessment $transition) {}

    public function store(StoreAssessmentRequest $request, string $currentTeam, CreateAssessment $create): RedirectResponse
    {
        $assessment = $create->handle(
            $this->user($request),
            $request->string('county_id')->toString(),
            $request->string('assessment_cycle_id')->toString(),
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => 'County assessment initiated with governed catalogue and scorecard lineage.']);

        return to_route('assessments.show', [$currentTeam, $assessment]);
    }

    public function show(Request $request, string $currentTeam, Assessment $assessment, AssessmentDetailData $detailData): Response
    {
        Gate::authorize(ProgrammePermission::ViewCountyData->value);
        $this->authorizeCounty($request, $assessment);

        return Inertia::render('assessments/show', [
            ...$detailData->for($assessment, $this->user($request)),
            'capabilities' => [
                'submit' => $request->user()?->can(ProgrammePermission::SubmitAssessment->value),
                'review' => $request->user()?->can(ProgrammePermission::ReviewAssessment->value),
                'score' => $request->user()?->can(ProgrammePermission::ScoreAssessment->value),
                'approve' => $request->user()?->can(ProgrammePermission::ApproveAssessment->value),
                'upload' => $request->user()?->can(ProgrammePermission::UploadEvidence->value),
            ],
        ]);
    }

    public function submit(Request $request, string $currentTeam, Assessment $assessment): RedirectResponse
    {
        return $this->transition($request, $assessment, ProgrammePermission::SubmitAssessment, [AssessmentStatus::Draft, AssessmentStatus::EvidenceCollection], AssessmentStatus::Submitted);
    }

    public function review(Request $request, string $currentTeam, Assessment $assessment): RedirectResponse
    {
        return $this->transition($request, $assessment, ProgrammePermission::ReviewAssessment, [AssessmentStatus::Submitted], AssessmentStatus::UnderAssessment);
    }

    public function score(ScoreAssessmentRequest $request, string $currentTeam, Assessment $assessment): RedirectResponse
    {
        $this->authorizeCounty($request, $assessment);
        abort_if($assessment->assessment_scorecard_version_id !== null, 409, 'Governed assessments must be calculated from verified criterion results.');
        abort_unless($assessment->status === AssessmentStatus::UnderAssessment, 409, 'Only an assessment under review can be scored.');
        $this->transition->handle($assessment, AssessmentStatus::Assessed, $this->user($request), (float) $request->validated('score'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Assessment score recorded.']);

        return back();
    }

    public function approve(Request $request, string $currentTeam, Assessment $assessment): RedirectResponse
    {
        if ($assessment->assessment_scorecard_version_id !== null) {
            abort_unless($assessment->score !== null && $assessment->completeness_percentage >= 100 && $assessment->attestation_status === 'attested', 409, 'A calculated, complete and attested assessment is required for approval.');
            abort_if($assessment->findings()->where('status', '!=', 'resolved')->exists(), 409, 'Resolve all findings before approval.');
        }

        return $this->transition($request, $assessment, ProgrammePermission::ApproveAssessment, [AssessmentStatus::Assessed], AssessmentStatus::Approved);
    }

    public function submitCriterionScore(SubmitCriterionScoreRequest $request, string $currentTeam, Assessment $assessment, AssessmentCriterion $criterion, SubmitCriterionScore $action): RedirectResponse
    {
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $criterion, $this->user($request), (float) $request->validated('score'), $request->validated('rationale'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Criterion score submitted.']);

        return back();
    }

    public function verifyCriterionScore(VerifyCriterionScoreRequest $request, string $currentTeam, Assessment $assessment, AssessmentCriterionResult $result, VerifyCriterionScore $action): RedirectResponse
    {
        abort_unless($result->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($result, $this->user($request), (float) $request->validated('score'), $request->validated('rationale'));

        return back();
    }

    public function overrideCriterionScore(OverrideCriterionScoreRequest $request, string $currentTeam, Assessment $assessment, AssessmentCriterionResult $result, OverrideCriterionScore $action): RedirectResponse
    {
        abort_unless($result->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($result, $this->user($request), (float) $request->validated('score'), $request->validated('reason'));

        return back();
    }

    public function calculate(Request $request, string $currentTeam, Assessment $assessment, CalculateAssessmentScore $action): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ScoreAssessment->value);
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $this->user($request));

        return back();
    }

    public function attest(AttestAssessmentRequest $request, string $currentTeam, Assessment $assessment, AttestAssessment $action): RedirectResponse
    {
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $this->user($request), $request->validated('attestor_title'), $request->validated('statement'));

        return back();
    }

    public function storeFinding(StoreAssessmentFindingRequest $request, string $currentTeam, Assessment $assessment, RecordAssessmentFinding $action): RedirectResponse
    {
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $this->user($request), [
            'assessment_criterion_id' => $request->filled('assessment_criterion_id') ? $request->string('assessment_criterion_id')->toString() : null,
            'code' => $request->string('code')->toString(),
            'severity' => $request->string('severity')->toString(),
            'title' => $request->string('title')->toString(),
            'description' => $request->string('description')->toString(),
            'assigned_to' => $request->filled('assigned_to') ? $request->string('assigned_to')->toString() : null,
            'response_due_at' => $request->validated('response_due_at'),
        ]);

        return back();
    }

    public function storeAppeal(StoreAssessmentAppealRequest $request, string $currentTeam, Assessment $assessment, SubmitAssessmentAppeal $action): RedirectResponse
    {
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $this->user($request), $request->validated('grounds'), $request->validated('requested_remedy'), $request->validated('assessment_criterion_id'));

        return back();
    }

    public function respondToFinding(RespondAssessmentFindingRequest $request, string $currentTeam, Assessment $assessment, AssessmentFinding $finding, RespondToAssessmentFinding $action): RedirectResponse
    {
        abort_unless($finding->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($finding, $this->user($request), $request->validated('response'));

        return back();
    }

    public function resolveFinding(ResolveAssessmentFindingRequest $request, string $currentTeam, Assessment $assessment, AssessmentFinding $finding, ResolveAssessmentFinding $action): RedirectResponse
    {
        abort_unless($finding->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($finding, $this->user($request), $request->validated('resolution'));

        return back();
    }

    public function decideAppeal(DecideAssessmentAppealRequest $request, string $currentTeam, Assessment $assessment, AssessmentAppeal $appeal, DecideAssessmentAppeal $action): RedirectResponse
    {
        abort_unless($appeal->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($appeal, $this->user($request), $request->validated('status'), $request->validated('decision'));

        return back();
    }

    public function publish(Request $request, string $currentTeam, Assessment $assessment, PublishAssessmentResult $action): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ApproveAssessment->value);
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $this->user($request));

        return back();
    }

    public function storeCorrectivePlan(StoreAssessmentCorrectivePlanRequest $request, string $currentTeam, Assessment $assessment, CreateAssessmentCorrectivePlan $action): RedirectResponse
    {
        $this->authorizeCounty($request, $assessment);
        $action->handle($assessment, $this->user($request), $request->payload());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Corrective plan submitted for independent review.']);

        return back();
    }

    public function reviewCorrectivePlan(ReviewAssessmentCorrectivePlanRequest $request, string $currentTeam, Assessment $assessment, AssessmentCorrectivePlan $plan, ReviewAssessmentCorrectivePlan $action): RedirectResponse
    {
        abort_unless($plan->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($plan, $this->user($request), $request->string('decision')->toString(), $request->string('review_note')->toString());

        return back();
    }

    public function storeCorrectiveUpdate(StoreAssessmentCorrectiveUpdateRequest $request, string $currentTeam, Assessment $assessment, AssessmentCorrectivePlan $plan, AssessmentCorrectiveAction $correctiveAction, RecordAssessmentCorrectiveUpdate $action): RedirectResponse
    {
        abort_unless($plan->assessment_id === $assessment->id && $correctiveAction->assessment_corrective_plan_id === $plan->id, 404);
        $this->authorizeCounty($request, $assessment);
        $document = AssessmentDocument::query()->findOrFail($request->string('assessment_document_id')->toString());
        $action->handle($correctiveAction, $document, $this->user($request), (float) $request->validated('progress_percentage'), $request->string('narrative')->toString());

        return back();
    }

    public function verifyCorrectiveUpdate(VerifyAssessmentCorrectiveUpdateRequest $request, string $currentTeam, Assessment $assessment, AssessmentCorrectivePlan $plan, AssessmentCorrectiveAction $correctiveAction, AssessmentCorrectiveUpdate $update, VerifyAssessmentCorrectiveUpdate $action): RedirectResponse
    {
        abort_unless($plan->assessment_id === $assessment->id && $correctiveAction->assessment_corrective_plan_id === $plan->id && $update->assessment_corrective_action_id === $correctiveAction->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($update, $this->user($request), $request->string('decision')->toString(), $request->string('decision_note')->toString());

        return back();
    }

    public function requestCorrectiveClosure(RequestAssessmentCorrectiveClosureRequest $request, string $currentTeam, Assessment $assessment, AssessmentCorrectivePlan $plan, RequestAssessmentCorrectiveClosure $action): RedirectResponse
    {
        abort_unless($plan->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($plan, $this->user($request));

        return back();
    }

    public function decideCorrectiveClosure(DecideAssessmentCorrectiveClosureRequest $request, string $currentTeam, Assessment $assessment, AssessmentCorrectivePlan $plan, DecideAssessmentCorrectiveClosure $action): RedirectResponse
    {
        abort_unless($plan->assessment_id === $assessment->id, 404);
        $this->authorizeCounty($request, $assessment);
        $action->handle($plan, $this->user($request), $request->string('decision')->toString(), $request->string('decision_reason')->toString());

        return back();
    }

    /** @param list<AssessmentStatus> $from */
    private function transition(Request $request, Assessment $assessment, ProgrammePermission $permission, array $from, AssessmentStatus $to): RedirectResponse
    {
        Gate::authorize($permission->value);
        $this->authorizeCounty($request, $assessment);
        abort_unless(in_array($assessment->status, $from), 409, 'This assessment is not in a valid state for that action.');
        $this->transition->handle($assessment, $to, $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => "Assessment moved to {$to->value}."]);

        return back();
    }

    private function authorizeCounty(Request $request, Assessment $assessment): void
    {
        abort_unless($this->user($request)->canAccessCounty($assessment->county), 403);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
