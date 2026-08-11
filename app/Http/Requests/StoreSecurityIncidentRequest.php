<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecurityIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'incident_lead_id' => ['required', 'uuid', 'exists:users,id'],
            'record_type' => ['required', 'in:live,exercise'],
            'playbook' => ['required', 'in:credential_compromise,ransomware,data_exfiltration,supplier_compromise,availability_disruption,malware,other'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'min:20', 'max:10000'],
            'affected_services' => ['required', 'string', 'max:2000'],
            'data_exposure' => ['required', 'in:none,suspected,confirmed,unknown'],
            'severity' => ['required', 'in:sev1,sev2,sev3,sev4'],
            'business_impact' => ['nullable', 'string', 'min:20', 'max:10000'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'exercise_objectives' => ['nullable', 'required_if:record_type,exercise', 'string', 'min:20', 'max:5000'],
            'detected_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
