<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentDocument;
use App\Models\DevolutionProject;
use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\IgrResolution;
use App\Models\InnovationReplication;
use App\Models\LearningLesson;
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

class DocumentAccess
{
    public function __construct(private SupportTicketAccess $supportTicketAccess) {}

    public function allows(User $user, AssessmentDocument $document): bool
    {
        if ($document->assessment_id !== null) {
            return $document->county_id !== null
                && $user->canAccessCounty($document->county)
                && $user->can(ProgrammePermission::ViewCountyData->value);
        }

        return $document->links()->with('subject')->get()->contains(function ($link) use ($user): bool {
            $subject = $link->subject;

            return ($subject instanceof TravelRequest || $subject instanceof DevolutionProject || $subject instanceof PartnerAgreement || $subject instanceof PartnerAgreementChangeRequest || $subject instanceof PartnerContribution || $subject instanceof PartnerCollaborationAction || $subject instanceof DswgMeeting || $subject instanceof DswgAction || $subject instanceof IgrResolution || $subject instanceof InnovationReplication || $subject instanceof LearningLesson || $subject instanceof ProgrammeEvaluation || $subject instanceof EvaluationFinding || $subject instanceof EvaluationFindingAction || $subject instanceof PerformancePlan || $subject instanceof PrivacyIncident || $subject instanceof SecurityIncident || $subject instanceof SupportTicket)
                && $this->allowsSubject($user, $subject);
        });
    }

    public function allowsSubject(User $user, TravelRequest|DevolutionProject|PartnerAgreement|PartnerAgreementChangeRequest|PartnerContribution|PartnerCollaborationAction|DswgMeeting|DswgAction|IgrResolution|InnovationReplication|LearningLesson|ProgrammeEvaluation|EvaluationFinding|EvaluationFindingAction|PerformancePlan|PrivacyIncident|SecurityIncident|SupportTicket $subject): bool
    {
        if ($subject instanceof TravelRequest) {
            if (! $user->can(ProgrammePermission::ViewTravelClearance->value)) {
                return false;
            }

            return $user->programmeRole()->hasNationalScope() || $subject->requester_id === $user->id || ($subject->county_id !== null && $user->canAccessCounty($subject->county));
        }

        if ($subject instanceof DevolutionProject) {
            return $user->can(ProgrammePermission::ViewProjects->value)
                && $subject->counties()->get()->contains(fn ($county): bool => $user->canAccessCounty($county));
        }

        if ($subject instanceof PartnerAgreement) {
            return $user->can(ProgrammePermission::ViewPartnerCoordination->value)
                && ($user->programmeRole()->hasNationalScope()
                    || $subject->partner->users()->whereKey($user)->exists()
                    || $subject->partner->counties()->get()->contains(fn ($county): bool => $user->canAccessCounty($county)));
        }

        if ($subject instanceof PartnerAgreementChangeRequest) {
            return $this->allowsSubject($user, $subject->agreement);
        }

        if ($subject instanceof PartnerContribution) {
            return $user->can(ProgrammePermission::ViewPartnerCoordination->value)
                && ($user->programmeRole()->hasNationalScope()
                    || $subject->partner->users()->whereKey($user)->exists()
                    || $subject->project->counties()->get()->contains(fn ($county): bool => $user->canAccessCounty($county)));
        }

        if ($subject instanceof PartnerCollaborationAction) {
            return $user->can(ProgrammePermission::ViewPartnerCoordination->value)
                && $user->canAccessCounty($subject->county);
        }

        if ($subject instanceof IgrResolution) {
            return $user->can(ProgrammePermission::ViewIgrResolutions->value)
                && ($user->programmeRole()->hasNationalScope()
                    || $subject->assignments()->where('user_id', $user->id)->exists()
                    || $subject->assignments()->whereNotNull('county_id')->with('county')->get()->contains(fn ($assignment): bool => $assignment->county !== null && $user->canAccessCounty($assignment->county)));
        }

        if ($subject instanceof InnovationReplication) {
            return $user->can(ProgrammePermission::ViewKnowledge->value)
                && $user->canAccessCounty($subject->targetCounty);
        }

        if ($subject instanceof LearningLesson) {
            $course = $subject->module->course;
            if (! $user->can(ProgrammePermission::ViewLearning->value)) {
                return false;
            }
            $withinScope = $course->county_id === null || $user->programmeRole()->hasNationalScope() || $user->canAccessCounty($course->county);
            if (! $withinScope) {
                return false;
            }

            return $user->canAny([ProgrammePermission::ManageLearning->value, ProgrammePermission::ReviewLearning->value])
                || ($course->status === 'published' && $course->enrollments()->where('user_id', $user->id)->whereIn('status', ['enrolled', 'in_progress', 'completed'])->exists());
        }

        if ($subject instanceof ProgrammeEvaluation) {
            return $user->can(ProgrammePermission::ViewMonitoringEvaluation->value)
                && ($user->programmeRole()->hasNationalScope()
                    || $subject->lead_evaluator_id === $user->id
                    || ($subject->county_id !== null && $user->canAccessCounty($subject->county)));
        }

        if ($subject instanceof EvaluationFinding) {
            return $this->allowsSubject($user, $subject->evaluation);
        }

        if ($subject instanceof EvaluationFindingAction) {
            return $this->allowsSubject($user, $subject->finding);
        }

        if ($subject instanceof PerformancePlan) {
            return $user->can(ProgrammePermission::ViewDepartmentalPerformance->value)
                && ($user->programmeRole()->hasNationalScope() || $subject->employee_id === $user->id || $subject->supervisor_id === $user->id);
        }

        if ($subject instanceof PrivacyIncident) {
            return $user->can(ProgrammePermission::ViewDataGovernance->value);
        }

        if ($subject instanceof SecurityIncident) {
            return $user->can(ProgrammePermission::ViewSecurityGovernance->value);
        }

        if ($subject instanceof SupportTicket) {
            return $this->supportTicketAccess->allows($user, $subject);
        }

        $meeting = $subject instanceof DswgAction ? $subject->meeting : $subject;

        return $user->can(ProgrammePermission::ViewDswg->value)
            && $meeting->workingGroup->counties()->get()->contains(fn ($county): bool => $user->canAccessCounty($county));
    }
}
