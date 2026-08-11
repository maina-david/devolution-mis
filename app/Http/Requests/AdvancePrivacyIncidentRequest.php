<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvancePrivacyIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'transition' => ['required', 'in:contain,assess,record_notifications,close'],
            'containment_actions' => ['nullable', 'required_if:transition,contain', 'string', 'min:20', 'max:10000'],
            'severity' => ['nullable', 'required_if:transition,assess', 'in:low,medium,high,critical'],
            'real_risk_of_harm' => ['nullable', 'required_if:transition,assess', 'in:yes,no'],
            'risk_assessment' => ['nullable', 'required_if:transition,assess', 'string', 'min:30', 'max:10000'],
            'regulator_notified_at' => ['nullable', Rule::requiredIf(fn (): bool => $this->string('transition')->is('record_notifications')), 'date', 'before_or_equal:now'],
            'regulator_notification_reference' => ['nullable', Rule::requiredIf(fn (): bool => $this->string('transition')->is('record_notifications')), 'string', 'max:255'],
            'regulator_delay_reason' => ['nullable', 'string', 'min:20', 'max:5000'],
            'subject_notification_decision' => ['nullable', Rule::requiredIf(fn (): bool => $this->string('transition')->is('record_notifications')), 'in:notified,not_required,delayed'],
            'data_subjects_notified_at' => ['nullable', 'required_if:subject_notification_decision,notified', 'date', 'before_or_equal:now'],
            'subject_notification_rationale' => ['nullable', 'required_if:subject_notification_decision,not_required,delayed', 'string', 'min:20', 'max:5000'],
            'root_cause' => ['nullable', 'required_if:transition,close', 'string', 'min:20', 'max:10000'],
            'remediation_actions' => ['nullable', 'required_if:transition,close', 'string', 'min:20', 'max:10000'],
            'closure_evidence_reference' => ['nullable', 'required_if:transition,close', 'string', 'max:255'],
        ];
    }
}
