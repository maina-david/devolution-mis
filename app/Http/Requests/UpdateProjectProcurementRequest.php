<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectProcurementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageProjects->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:planned,advertised,evaluation,awarded,contracted,completed,cancelled'],
            'estimated_value' => ['required', 'numeric', 'min:0'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'planned_notice_date' => ['nullable', 'date'],
            'award_date' => ['nullable', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'contract_reference' => ['nullable', 'string', 'max:255'],
            'amendment_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
