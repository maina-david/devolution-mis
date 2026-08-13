<?php

namespace App\Http\Controllers;

use App\Actions\RecordSupportTicketActivity;
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
use App\Models\SupportTicket;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\DocumentAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LinkedDocumentController extends Controller
{
    public function storeSupportTicket(
        StoreLinkedDocumentRequest $request,
        SupportTicket $supportTicket,
        StoreLinkedDocument $storeDocument,
        DocumentAccess $documentAccess,
        RecordSupportTicketActivity $recordActivity,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $supportTicket), 403);
        abort_if($supportTicket->status === 'closed', 409, __('linked-documents.errors.support_closed'));
        abort_unless($user->id === $supportTicket->requester_id || $user->id === $supportTicket->assigned_to || $user->can(ProgrammePermission::ManageSupportTickets->value), 403);
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_unless(in_array($recordPurpose, ['request', 'investigation', 'resolution'], true), 422, __('linked-documents.errors.support_purpose'));
        abort_if($recordPurpose === 'resolution' && ! in_array($supportTicket->status, ['in_progress', 'resolved'], true), 409, __('linked-documents.errors.support_resolution_stage'));
        $document = $storeDocument->handle($supportTicket, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "support-ticket-{$recordPurpose}-evidence", 'county_id' => $supportTicket->county_id]);
        $supportTicket->update(['last_activity_at' => now(), 'reminder_sent_at' => null]);
        $recordActivity->handle(
            $supportTicket,
            $user,
            'document_uploaded',
            $supportTicket->status,
            $supportTicket->status,
            __('linked-documents.activity.support_uploaded'),
            [
                'document_id' => $document->id,
                'document_checksum' => $document->content_checksum,
                'record_purpose' => $recordPurpose,
                'scan_status' => $document->scan_status,
            ],
        );
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.support_uploaded')]);

        return back();
    }

    public function storeInnovationReplication(StoreLinkedDocumentRequest $request, InnovationReplication $replication, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $replication), 403);
        abort_unless($user->id === $replication->accountable_user_id || $user->can(ProgrammePermission::ManageKnowledge->value), 403);
        abort_unless(in_array($replication->status, ['adapting', 'piloting'], true), 409, __('linked-documents.errors.replication_stage'));
        $storeDocument->handle($replication, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'innovation-replication-evidence', 'county_id' => $replication->target_county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.replication_uploaded')]);

        return back();
    }

    public function storeSecurityIncident(StoreLinkedDocumentRequest $request, SecurityIncident $securityIncident, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $securityIncident) && $user->can(ProgrammePermission::ManageSecurityGovernance->value), 403);
        abort_if($securityIncident->status === 'closed', 409, __('linked-documents.errors.incident_closed'));
        $recordPurpose = $request->string('record_purpose')->toString();
        $allowedPurposes = ['investigation', 'containment', 'recovery', 'closure'];
        abort_unless(in_array($recordPurpose, $allowedPurposes, true), 422, __('linked-documents.errors.security_purpose'));
        abort_if($recordPurpose === 'containment' && ! in_array($securityIncident->status, ['acknowledged', 'contained'], true), 409, __('linked-documents.errors.containment_stage'));
        abort_if($recordPurpose === 'recovery' && ! in_array($securityIncident->status, ['eradicated', 'recovered'], true), 409, __('linked-documents.errors.recovery_stage'));
        abort_if($recordPurpose === 'closure' && $securityIncident->status !== 'recovered', 409, __('linked-documents.errors.security_closure_stage'));
        $storeDocument->handle($securityIncident, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "security-incident-{$recordPurpose}-evidence", 'county_id' => null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.security_uploaded')]);

        return back();
    }

    public function storePrivacyIncident(StoreLinkedDocumentRequest $request, PrivacyIncident $privacyIncident, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $privacyIncident) && $user->can(ProgrammePermission::ManageDataGovernance->value), 403);
        abort_if($privacyIncident->status === 'closed', 409, __('linked-documents.errors.incident_closed'));
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_unless(in_array($recordPurpose, ['investigation', 'notification', 'closure'], true), 422, __('linked-documents.errors.privacy_purpose'));
        abort_if($recordPurpose === 'notification' && ! in_array($privacyIncident->status, ['notification_required', 'remediation'], true), 409, __('linked-documents.errors.notification_stage'));
        abort_if($recordPurpose === 'closure' && $privacyIncident->status !== 'remediation', 409, __('linked-documents.errors.privacy_closure_stage'));
        $storeDocument->handle($privacyIncident, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "privacy-incident-{$recordPurpose}-evidence", 'county_id' => $privacyIncident->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.privacy_uploaded')]);

        return back();
    }

    public function storeTravel(StoreLinkedDocumentRequest $request, TravelRequest $travelRequest, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->id === $travelRequest->requester_id && $documentAccess->allowsSubject($user, $travelRequest), 403);
        abort_unless($travelRequest->status === 'draft', 409, __('linked-documents.errors.travel_submitted'));
        $storeDocument->handle($travelRequest, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'travel-supporting-document', 'county_id' => $travelRequest->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.travel_uploaded')]);

        return back();
    }

    public function storeProject(StoreLinkedDocumentRequest $request, DevolutionProject $project, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $project), 403);
        abort_if($project->status === 'closed' || $project->lifecycle_stage === 'closed', 409, __('linked-documents.errors.project_closed'));
        $recordPurpose = $request->string('record_purpose', 'lifecycle_record')->toString();
        abort_if($recordPurpose === 'closure_report' && $project->lifecycle_stage !== 'execution', 409, __('linked-documents.errors.project_closure_stage'));
        $purpose = $recordPurpose === 'closure_report' ? 'project-closure-report' : 'project-lifecycle-document';
        $storeDocument->handle($project, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => $project->lead_county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.project_uploaded')]);

        return back();
    }

    public function storePartnerAgreement(StoreLinkedDocumentRequest $request, PartnerAgreement $agreement, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $agreement), 403);
        abort_unless($agreement->status === 'draft', 409, __('linked-documents.errors.agreement_submitted'));
        if (! $user->can(ProgrammePermission::ManagePartners->value)) {
            abort_unless($agreement->partner->users()->whereKey($user)->exists(), 403);
        }
        $countyId = $agreement->partner->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($agreement, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-agreement-record', 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.agreement_uploaded')]);

        return back();
    }

    public function storeDswgMeeting(StoreLinkedDocumentRequest $request, DswgMeeting $meeting, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $meeting), 403);
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_if($meeting->status === 'closed', 409, __('linked-documents.errors.meeting_closed'));
        abort_if($recordPurpose === 'agenda' && $meeting->status !== 'scheduled', 409, __('linked-documents.errors.agenda_recorded'));
        abort_if($recordPurpose === 'minutes' && $meeting->status !== 'minutes_pending', 409, __('linked-documents.errors.minutes_stage'));
        $countyId = $meeting->workingGroup->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($meeting, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => "dswg-{$recordPurpose}-record", 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.meeting_uploaded')]);

        return back();
    }

    public function storePartnerAgreementChange(StoreLinkedDocumentRequest $request, PartnerAgreementChangeRequest $changeRequest, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $changeRequest), 403);
        abort_if($changeRequest->decision()->exists(), 409, __('linked-documents.errors.agreement_change_decided'));
        abort_unless($user->can(ProgrammePermission::ManagePartners->value) || $changeRequest->requested_by === $user->id, 403);
        $countyId = $changeRequest->agreement->partner->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($changeRequest, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-agreement-change-evidence', 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.agreement_change_uploaded')]);

        return back();
    }

    public function storePartnerContribution(StoreLinkedDocumentRequest $request, PartnerContribution $contribution, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $contribution), 403);
        abort_unless($user->can(ProgrammePermission::ManagePartners->value) || $contribution->reported_by === $user->id || $contribution->partner->users()->whereKey($user)->exists(), 403);
        $storeDocument->handle($contribution, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-contribution-reconciliation-evidence', 'county_id' => $contribution->project->lead_county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.contribution_uploaded')]);

        return back();
    }

    public function storePartnerCollaborationAction(StoreLinkedDocumentRequest $request, PartnerCollaborationAction $action, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $action), 403);
        abort_unless($user->can(ProgrammePermission::ManagePartners->value) || $action->accountable_user_id === $user->id, 403);
        abort_if($action->status === 'completed', 409, __('linked-documents.errors.partner_action_completed'));
        $storeDocument->handle($action, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'partner-collaboration-action-evidence', 'county_id' => $action->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.collaboration_action_uploaded')]);

        return back();
    }

    public function storeDswgAction(StoreLinkedDocumentRequest $request, DswgAction $action, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $action), 403);
        abort_unless($user->can(ProgrammePermission::ManageDswg->value) || $action->accountable_user_id === $user->id, 403);
        abort_unless($action->status === 'in_progress', 409, __('linked-documents.errors.dswg_action_stage'));
        $countyId = $action->county_id ?? $action->meeting->workingGroup->counties()->orderBy('code')->value('counties.id');
        $storeDocument->handle($action, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'dswg-action-evidence', 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.action_uploaded')]);

        return back();
    }

    public function storeIgrResolution(StoreLinkedDocumentRequest $request, IgrResolution $resolution, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $resolution), 403);
        abort_unless(in_array($resolution->status, ['open', 'in_progress'], true), 409, __('linked-documents.errors.igr_closed'));
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_if($recordPurpose === 'resolution' && $resolution->status !== 'open', 409, __('linked-documents.errors.igr_resolution_started'));
        $countyId = $resolution->assignments()->whereNotNull('county_id')->orderBy('created_at')->value('county_id');
        $purpose = $recordPurpose === 'resolution' ? 'igr-resolution-record' : 'igr-implementation-evidence';
        $storeDocument->handle($resolution, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => $countyId]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.igr_uploaded')]);

        return back();
    }

    public function storeProgrammeEvaluation(StoreLinkedDocumentRequest $request, ProgrammeEvaluation $evaluation, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $evaluation), 403);
        abort_unless(in_array($evaluation->status, ['planned', 'in_progress'], true), 409, __('linked-documents.errors.evaluation_closed'));
        $recordPurpose = $request->string('record_purpose')->toString();
        abort_if($recordPurpose === 'terms_of_reference' && $evaluation->status !== 'planned', 409, __('linked-documents.errors.evaluation_tor_started'));
        abort_if($recordPurpose === 'evaluation_report' && $evaluation->status !== 'in_progress', 409, __('linked-documents.errors.evaluation_report_stage'));
        $purpose = match ($recordPurpose) {
            'terms_of_reference' => 'programme-evaluation-tor',
            'evaluation_report' => 'programme-evaluation-report',
            default => 'programme-evaluation-supporting',
        };
        $storeDocument->handle($evaluation, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => $evaluation->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.evaluation_uploaded')]);

        return back();
    }

    public function storeEvaluationFinding(StoreLinkedDocumentRequest $request, EvaluationFinding $finding, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $finding), 403);
        abort_unless($finding->status === 'open' && $finding->accountable_owner_id === $user->id, 403);
        $storeDocument->handle($finding, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'evaluation-finding-response-evidence', 'county_id' => $finding->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.finding_uploaded')]);

        return back();
    }

    public function storeEvaluationFindingAction(StoreLinkedDocumentRequest $request, EvaluationFindingAction $action, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $action), 403);
        abort_unless($action->finding->status === 'open' && $action->status !== 'completed' && $action->accountable_owner_id === $user->id, 403);
        $storeDocument->handle($action, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'evaluation-finding-action-evidence', 'county_id' => $action->finding->county_id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.action_uploaded')]);

        return back();
    }

    public function storePerformancePlan(StoreLinkedDocumentRequest $request, PerformancePlan $performancePlan, StoreLinkedDocument $storeDocument, DocumentAccess $documentAccess): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($documentAccess->allowsSubject($user, $performancePlan), 403);

        $recordPurpose = $request->string('record_purpose')->toString();
        abort_unless(in_array($recordPurpose, ['goal_plan', 'self_review_evidence', 'final_appraisal'], true), 422, __('linked-documents.errors.performance_purpose'));

        $expectedStatus = match ($recordPurpose) {
            'goal_plan' => 'draft',
            'self_review_evidence' => 'self_review',
            'final_appraisal' => 'supervisor_review',
        };
        abort_unless($performancePlan->status === $expectedStatus, 409, __('linked-documents.errors.performance_stage'));

        $isEmployeeRecord = in_array($recordPurpose, ['goal_plan', 'self_review_evidence'], true);
        abort_unless($isEmployeeRecord ? $performancePlan->employee_id === $user->id : $performancePlan->supervisor_id === $user->id, 403);

        $purpose = match ($recordPurpose) {
            'goal_plan' => 'performance-goal-plan',
            'self_review_evidence' => 'performance-self-review-evidence',
            'final_appraisal' => 'performance-final-appraisal',
        };
        $storeDocument->handle($performancePlan, $user, $request->file('document'), ['title' => $request->string('title')->toString(), 'category' => $request->string('category')->toString(), 'source_type' => $request->string('source_type')->toString(), 'purpose' => $purpose, 'county_id' => null]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('linked-documents.outcomes.performance_uploaded')]);

        return back();
    }
}
