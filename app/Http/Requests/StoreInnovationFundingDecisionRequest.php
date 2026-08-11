<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInnovationFundingDecisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageKnowledge->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['decision' => ['required', Rule::in(['approved', 'declined', 'not_required'])], 'amount' => ['required', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3', Rule::in(ReferenceCatalogue::currencies())], 'funding_type' => ['required', Rule::in(['grant', 'in_kind', 'blended', 'not_applicable'])], 'decision_reference' => ['required', 'string', 'max:255'], 'rationale' => ['required', 'string', 'min:30', 'max:5000']];
    }
}
