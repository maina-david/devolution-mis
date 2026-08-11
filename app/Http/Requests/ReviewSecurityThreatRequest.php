<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewSecurityThreatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['decision' => ['required', 'in:accepted,rejected'], 'treatment_status' => ['required', 'in:planned,in_progress,mitigated,accepted'], 'residual_likelihood' => ['required', 'integer', 'between:1,5'], 'residual_impact' => ['required', 'integer', 'between:1,5'], 'risk_acceptance_reference' => ['nullable', 'required_if:treatment_status,accepted', 'string', 'max:255'], 'review_note' => ['required', 'string', 'min:20', 'max:5000'], 'evidence_references' => ['nullable', 'string', 'max:3000']];
    }
}
