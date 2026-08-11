<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('programmes', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lead_organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')],
            'sector_id' => ['nullable', 'uuid', Rule::exists('sectors', 'id')],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['planned', 'active', 'on_hold', 'completed', 'cancelled'])],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'uppercase', Rule::in(ReferenceCatalogue::currencies())],
        ];
    }
}
