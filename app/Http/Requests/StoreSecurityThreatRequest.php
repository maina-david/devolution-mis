<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecurityThreatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['owner_id' => ['required', 'uuid', 'exists:users,id'], 'reference' => ['required', 'string', 'max:80', 'unique:security_threats,reference'], 'title' => ['required', 'string', 'max:255'], 'stride_category' => ['required', 'in:spoofing,tampering,repudiation,information_disclosure,denial_of_service,elevation_of_privilege,supply_chain,privacy'], 'asset' => ['required', 'string', 'max:255'], 'scenario' => ['required', 'string', 'min:20', 'max:5000'], 'threat_actor' => ['nullable', 'string', 'max:255'], 'entry_points' => ['required', 'string', 'max:3000'], 'likelihood' => ['required', 'integer', 'between:1,5'], 'impact' => ['required', 'integer', 'between:1,5'], 'existing_controls' => ['required', 'string', 'max:5000'], 'treatment_plan' => ['required', 'string', 'max:5000'], 'review_due_at' => ['required', 'date', 'after:today'], 'evidence_references' => ['nullable', 'string', 'max:3000']];
    }
}
