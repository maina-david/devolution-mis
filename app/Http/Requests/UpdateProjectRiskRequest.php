<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRiskRequest extends FormRequest
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
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'probability' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],
            'residual_probability' => ['nullable', 'integer', 'between:1,5'],
            'residual_impact' => ['nullable', 'integer', 'between:1,5'],
            'mitigation' => ['required', 'string', 'max:5000'],
            'status' => ['required', 'in:open,monitoring,mitigated,closed'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'review_due_date' => ['nullable', 'date'],
            'amendment_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
