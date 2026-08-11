<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectMilestoneRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'planned_start_date' => ['required', 'date'],
            'planned_end_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'progress' => ['required', 'numeric', 'between:0,100'],
            'status' => ['required', 'in:not_started,in_progress,delayed,completed'],
            'owner_id' => ['nullable', 'uuid', 'exists:users,id'],
            'dependencies' => ['nullable', 'array'],
            'dependencies.*' => ['uuid', 'distinct', 'exists:project_milestones,id'],
            'amendment_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
