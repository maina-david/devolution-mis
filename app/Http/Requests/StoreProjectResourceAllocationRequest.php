<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectResourceAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageProjects->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'project_resource_id' => ['required', 'uuid', 'exists:project_resources,id'],
            'project_milestone_id' => ['required', 'uuid', 'exists:project_milestones,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.now()->addYears(3)->toDateString()],
            'planned_units_per_day' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
