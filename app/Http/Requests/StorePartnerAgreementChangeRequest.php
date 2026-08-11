<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerAgreementChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManagePartners->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->string('change_type')->toString();

        return [
            'change_type' => ['required', Rule::in(['amendment', 'renewal', 'suspension', 'termination'])],
            'reason' => ['required', 'string', 'min:20', 'max:5000'],
            'effective_on' => ['required', 'date', 'after_or_equal:today'],
            'title' => [Rule::requiredIf($type === 'amendment'), 'nullable', 'string', 'max:255'],
            'summary' => [Rule::requiredIf($type === 'amendment'), 'nullable', 'string', 'max:5000'],
            'ends_on' => [Rule::requiredIf(in_array($type, ['amendment', 'renewal'], true)), 'nullable', 'date', 'after_or_equal:effective_on'],
            'committed_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:90000000000000000'],
        ];
    }
}
