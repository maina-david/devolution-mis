<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLinkedDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->routeIs('travel-clearance.documents.store')) {
            return $this->user()?->can(ProgrammePermission::SubmitTravelRequests->value) === true;
        }

        if ($this->routeIs('projects.documents.store')) {
            return $this->user()?->canAny([
                ProgrammePermission::ManageProjects->value,
                ProgrammePermission::SubmitProjectUpdates->value,
            ]) === true;
        }

        if ($this->routeIs('partners.agreements.documents.store') || $this->routeIs('partners.agreement-changes.documents.store') || $this->routeIs('partners.contributions.documents.store') || $this->routeIs('partners.collaboration-actions.documents.store')) {
            return $this->user()?->canAny([
                ProgrammePermission::ManagePartners->value,
                ProgrammePermission::SubmitPartnerData->value,
            ]) === true;
        }

        if ($this->routeIs('dswg.meetings.documents.store')) {
            return $this->user()?->can(ProgrammePermission::ManageDswg->value) === true;
        }

        if ($this->routeIs('dswg.actions.documents.store')) {
            return $this->user()?->can(ProgrammePermission::ManageDswgActions->value) === true;
        }

        if ($this->routeIs('igr-resolutions.documents.store')) {
            return $this->user()?->canAny([
                ProgrammePermission::ManageIgrResolutions->value,
                ProgrammePermission::UpdateIgrResolutions->value,
            ]) === true;
        }

        if ($this->routeIs('monitoring-evaluation.evaluations.documents.store')) {
            return $this->user()?->can(ProgrammePermission::ManageIndicators->value) === true;
        }

        if ($this->routeIs('monitoring-evaluation.findings.documents.store') || $this->routeIs('monitoring-evaluation.finding-actions.documents.store')) {
            return $this->user()?->can(ProgrammePermission::SubmitIndicatorData->value) === true;
        }

        if ($this->routeIs('departmental-performance.plans.documents.store')) {
            return $this->user()?->canAny([
                ProgrammePermission::SubmitPerformancePlans->value,
                ProgrammePermission::ReviewPerformancePlans->value,
            ]) === true;
        }

        if ($this->routeIs('data-governance.privacy-incidents.documents.store')) {
            return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
        }

        if ($this->routeIs('security-governance.incidents.documents.store')) {
            return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
        }

        if ($this->routeIs('support-desk.documents.store')) {
            return $this->user()?->canAny([
                ProgrammePermission::SubmitSupportTickets->value,
                ProgrammePermission::ManageSupportTickets->value,
                ProgrammePermission::ResolveSupportTickets->value,
            ]) === true;
        }

        if ($this->routeIs('knowledge.innovation-replications.documents.store')) {
            return $this->user()?->canAny([ProgrammePermission::ContributeKnowledge->value, ProgrammePermission::ManageKnowledge->value]) === true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'source_type' => ['required', 'in:scanned,digital'],
            'record_purpose' => [Rule::requiredIf($this->routeIs('dswg.meetings.documents.store') || $this->routeIs('igr-resolutions.documents.store') || $this->routeIs('monitoring-evaluation.evaluations.documents.store') || $this->routeIs('departmental-performance.plans.documents.store') || $this->routeIs('data-governance.privacy-incidents.documents.store') || $this->routeIs('security-governance.incidents.documents.store') || $this->routeIs('support-desk.documents.store')), 'nullable', Rule::in(['agenda', 'minutes', 'supporting', 'resolution', 'implementation_evidence', 'terms_of_reference', 'evaluation_report', 'goal_plan', 'self_review_evidence', 'final_appraisal', 'lifecycle_record', 'closure_report', 'investigation', 'notification', 'containment', 'recovery', 'closure', 'request'])],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,tif,tiff,doc,docx,xls,xlsx,csv,txt', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp,image/tiff,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain', 'max:20480'],
        ];
    }
}
