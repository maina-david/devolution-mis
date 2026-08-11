<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DecideAccessReviewItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::CertifyAccess->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['decision' => ['required', 'in:retain,revoke,remediate'], 'rationale' => ['required', 'string', 'min:20', 'max:5000'], 'remediation_action' => ['nullable', 'required_if:decision,remediate', 'string', 'max:5000'], 'remediation_due_at' => ['nullable', 'required_if:decision,remediate', 'date', 'after:today']];
    }
}
