<?php

namespace App\Http\Controllers;

use App\Actions\StoreLinkedDocument;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreLinkedDocumentRequest;
use App\Models\DevolutionProject;
use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\IgrResolution;
use App\Models\InnovationReplication;
use App\Models\PartnerAgreement;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerContribution;
use App\Models\PerformancePlan;
use App\Models\PrivacyIncident;
use App\Models\ProgrammeEvaluation;
use App\Models\SecurityIncident;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\DocumentAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LinkedDocumentController extends Controller
{
    public function storeInnovationReplication(StoreLinkedDocumentRequest $request, string $currentTeam, InnovationReplication $replication, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $replication), 403);
        abort_unless($user->id === $replication->accountable_user_id || $user->can(ProgrammePermission::ManageKnowledge->value), 403);
        abort_unless(in_array($replication->status, ['adapting', 'piloting'], true), 409, 'Replication evidence is accepted only during adaptation or piloting.');
        $storeDocument->handle($replication, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'innovation-replication-evidence', 'county_id' => $replication->target_county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Replication evidence uploaded securely for independent verification.']);

        return back();
    }

    public function storeSecurityIncident(StoreLinkedDocumentRequest $request, string $currentTeam, SecurityIncident $securityIncident, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $securityIncident) && $user->can(ProgrammePermission::ManageSecurityGovernance->value), 403);
        abort_if($securityIncident->status === 'closed', 409, 'Incident evidence is locked after independent closure.');
        $recordPurpose = $request->string('record_purpose')->toString();
        $allowedPurposes = ['investigation', 'containment', 'recovery', 'closure'];
        abort_unless(in_array($recordPurpose, $allowedPurposes, true), 422, 'Unsupported security incident record purpose.');
        abort_if($recordPurpose === 'containment' && ! in_array($securityIncident->status, ['acknowledged', 'contained'], true), 409, 'Containment evidence is accepted only during acknowledgement and containment.');
        abort_if($recordPurpose === 'recovery' && ! in_array($securityIncident->status, ['eradicated', 'recovered'], true), 409, 'Recovery evidence is accepted only after eradication.');
        abort_if($recordPurpose === 'closure' && $securityIncident->status !== 'recovered', 409, 'Closure evidence is accepted only after recovery.');
        $storeDocument->handle($securityIncident, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "security-incident-{$recordPurpose}-evidence", 'county_id' => null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Security incident evidence uploaded securely.']);

        return back();
    }

    public function storePrivacyIncident(StoreLinkedDocumentRequest $request, string $currentTeam, PrivacyIncident $privacyIncident, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $privacyIncident) && $user->can(ProgrammePermission::ManageDataGovernance->value), 403);
        abort_if($privacyIncident->status === 'closed', 409, 'Incident evidence is locked after independent closure.');
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_unless(in_array($recordPurpose, ['investigation', 'notification', 'closure'], true), 422, 'Unsupported incident record purpose.');
        abort_if($recordPurpose === 'notification' && ! in_array($privacyIncident->status, ['notification_required', 'remediation'], true), 409, 'Notification evidence may be uploaded only after independent risk assessment.');
        abort_if($recordPurpose === 'closure' && $privacyIncident->status !== 'remediation', 409, 'Closure evidence may be uploaded only during remediation.');
        $storeDocument->handle($privacyIncident, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "privacy-incident-{$recordPurpose}-evidence", 'county_id' => $privacyIncident->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Incident evidence uploaded securely.']);

        return back();
    }

    public function storeTravel(StoreLinkedDocumentRequest $request, string $currentTeam, TravelRequest $travelRequest, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->id === $travelRequest->requester_id && $documentAccess->allowsSubject($user, $travelRequest), 403);
        abort_unless($travelRequest->status === 'draft', 409, 'Supporting documents are locked after submission.');
        $storeDocument->handle($travelRequest, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'travel-supporting-document', 'county_id' => $travelRequest->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supporting document uploaded securely.']);

        return back();
    }

    public function storeProject(StoreLinkedDocumentRequest $request, string $currentTeam, DevolutionProject $project, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $project), 403);
        abort_if($project->status === 'closed' || $project->lifecycle_stage === 'closed', 409, 'Project documents are locked after closure.');
        $recordPurpose = $request->string('record_purpose', 'lifecycle_record')->toString();
        abort_if($recordPurpose === 'closure_report' && $project->lifecycle_stage !== 'execution', 409, 'The project closure report may be uploaded only during execution.');
        $purpose = $recordPurpose === 'closure_report' ? 'project-closure-report' : 'project-lifecycle-document';
        $storeDocument->handle($project, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => $project->lead_county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Project record uploaded securely.']);

        return back();
    }

    public function storePartnerAgreement(StoreLinkedDocumentRequest $request, string $currentTeam, PartnerAgreement $agreement, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $agreement), 403);
        abort_unless($agreement->status === 'draft', 409, 'Agreement records are locked after submission.');
        if (! $user->can(ProgrammePermission::ManagePartners->value)) {
            abort_unless($agreement->partner->users()->whereKey($user)->exists(), 403);
        }
        $countyId = $agreement->partner->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($agreement, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-agreement-record', 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agreement record uploaded securely.']);

        return back();
    }

    public function storeDswgMeeting(StoreLinkedDocumentRequest $request, string $currentTeam, DswgMeeting $meeting, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $meeting), 403);
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_if($meeting->status === 'closed', 409, 'Meeting records are locked after minutes approval.');
        abort_if($recordPurpose === 'agenda' && $meeting->status !== 'scheduled', 409, 'Agenda records are locked after outcomes are recorded.');
        abort_if($recordPurpose === 'minutes' && $meeting->status !== 'minutes_pending', 409, 'Minutes records may be uploaded only after outcomes are recorded.');
        $countyId = $meeting->workingGroup->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($meeting, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "dswg-{$recordPurpose}-record", 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meeting record uploaded securely.']);

        return back();
    }

    public function storePartnerAgreementChange(StoreLinkedDocumentRequest $request, string $currentTeam, PartnerAgreementChangeRequest $changeRequest, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $changeRequest), 403);
        abort_if($changeRequest->decision()->exists(), 409, 'Change evidence is locked after a decision.');
        abort_unless($user->can(ProgrammePermission::ManagePartners->value) || $changeRequest->requested_by === $user->id, 403);
        $countyId = $changeRequest->agreement->partner->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($changeRequest, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-agreement-change-evidence', 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agreement change evidence uploaded securely.']);

        return back();
    }

    public function storePartnerContribution(StoreLinkedDocumentRequest $request, string $currentTeam, PartnerContribution $contribution, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $contribution), 403);
        abort_unless($user->can(ProgrammePermission::ManagePartners->value) || $contribution->reported_by === $user->id || $contribution->partner->users()->whereKey($user)->exists(), 403);
        $storeDocument->handle($contribution, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-contribution-reconciliation-evidence', 'county_id' => $contribution->project->lead_county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contribution evidence uploaded securely for independent reconciliation.']);

        return back();
    }

    public function storePartnerCollaborationAction(StoreLinkedDocumentRequest $request, string $currentTeam, PartnerCollaborationAction $action, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $action), 403);
        abort_unless($user->can(ProgrammePermission::ManagePartners->value) || $action->accountable_user_id === $user->id, 403);
        abort_if($action->status === 'completed', 409, 'Action evidence is locked after verified completion.');
        $storeDocument->handle($action, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-collaboration-action-evidence', 'county_id' => $action->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Collaboration action evidence uploaded securely.']);

        return back();
    }

    public function storeDswgAction(StoreLinkedDocumentRequest $request, string $currentTeam, DswgAction $action, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $action), 403);
        abort_unless($user->can(ProgrammePermission::ManageDswg->value) || $action->accountable_user_id === $user->id, 403);
        abort_unless($action->status === 'in_progress', 409, 'Action evidence may be uploaded only while implementation is in progress.');
        $countyId = $action->county_id ?? $action->meeting->workingGroup->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($action, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'dswg-action-evidence', 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Action evidence uploaded securely.']);

        return back();
    }

    public function storeIgrResolution(StoreLinkedDocumentRequest $request, string $currentTeam, IgrResolution $resolution, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $resolution), 403);
        abort_unless(in_array($resolution->status, ['open', 'in_progress'], true), 409, 'Resolution records are locked during closure review and after closure.');
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_if($recordPurpose === 'resolution' && $resolution->status !== 'open', 409, 'The adopted resolution record is locked after implementation starts.');
        $countyId = $resolution->assignments()->whereNotNull('county_id')->orderBy('created_at')->value('county_id');
        $purpose = $recordPurpose === 'resolution' ? 'igr-resolution-record' : 'igr-implementation-evidence';
        $storeDocument->handle($resolution, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'IGR record uploaded securely.']);

        return back();
    }

    public function storeProgrammeEvaluation(StoreLinkedDocumentRequest $request, string $currentTeam, ProgrammeEvaluation $evaluation, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $evaluation), 403);
        abort_unless(in_array($evaluation->status, ['planned', 'in_progress'], true), 409, 'Evaluation records are locked during review and after approval.');
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_if($recordPurpose === 'terms_of_reference' && $evaluation->status !== 'planned', 409, 'Terms of reference are locked after implementation starts.');
        abort_if($recordPurpose === 'evaluation_report' && $evaluation->status !== 'in_progress', 409, 'Evaluation reports may be uploaded only while the study is in progress.');
        $purpose = match ($recordPurpose) {
            'terms_of_reference' => 'programme-evaluation-tor',
            'evaluation_report' => 'programme-evaluation-report',
            default => 'programme-evaluation-supporting',
        };
        $storeDocument->handle($evaluation, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => $evaluation->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation record uploaded securely.']);

        return back();
    }

    public function storeEvaluationFinding(StoreLinkedDocumentRequest $request, string $currentTeam, EvaluationFinding $finding, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $finding), 403);
        abort_unless($finding->status === 'open' && $finding->accountable_owner_id === $user->id, 403);
        $storeDocument->handle($finding, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'evaluation-finding-response-evidence', 'county_id' => $finding->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Finding response evidence uploaded securely.']);

        return back();
    }

    public function storeEvaluationFindingAction(StoreLinkedDocumentRequest $request, string $currentTeam, EvaluationFindingAction $action, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $action), 403);
        abort_unless($action->finding->status === 'open' && $action->status !== 'completed' && $action->accountable_owner_id === $user->id, 403);
        $storeDocument->handle($action, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'evaluation-finding-action-evidence', 'county_id' => $action->finding->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Action evidence uploaded securely.']);

        return back();
    }

    public function storePerformancePlan(StoreLinkedDocumentRequest $request, string $currentTeam, PerformancePlan $performancePlan, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $performancePlan), 403);

        $recordPurpose = $request->string('record_purpose')->toString();
        abort_unless(in_array($recordPurpose, ['goal_plan', 'self_review_evidence', 'final_appraisal'], true), 422, 'Unsupported performance record purpose.');

        $expectedStatus = match ($recordPurpose) {
            'goal_plan' => 'draft',
            'self_review_evidence' => 'self_review',
            'final_appraisal' => 'supervisor_review',
        };
        abort_unless($performancePlan->status === $expectedStatus, 409, 'Performance records are locked outside their applicable lifecycle stage.');

        $isEmployeeRecord = in_array($recordPurpose, ['goal_plan', 'self_review_evidence'], true);
        abort_unless($isEmployeeRecord ? $performancePlan->employee_id === $user->id : $performancePlan->supervisor_id === $user->id, 403);

        $purpose = match ($recordPurpose) {
            'goal_plan' => 'performance-goal-plan',
            'self_review_evidence' => 'performance-self-review-evidence',
            'final_appraisal' => 'performance-final-appraisal',
        };
        $storeDocument->handle($performancePlan, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Performance record uploaded securely.']);

        return back();
    }
}
