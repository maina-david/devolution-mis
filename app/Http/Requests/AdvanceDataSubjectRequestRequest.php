<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceDataSubjectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:verify_identity,start_review,complete,reject'], 'identity_evidence_reference' => ['nullable', 'required_if:transition,verify_identity', 'string', 'max:255'], 'decision' => ['nullable', 'required_if:transition,complete,reject', 'string', 'max:5000'], 'decision_reason' => ['nullable', 'required_if:transition,complete,reject', 'string', 'min:20', 'max:5000'], 'response_evidence_reference' => ['nullable', 'required_if:transition,complete', 'string', 'max:255']];
    }
}
