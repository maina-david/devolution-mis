<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishIntegrationContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageIntegrations->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['source_owner_approval_reference' => ['nullable', 'string', 'max:255'], 'data_sharing_agreement_reference' => ['nullable', 'string', 'max:255'], 'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after:effective_from']];
    }
}
