<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\DevolutionProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageProjects->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project instanceof DevolutionProject ? $project->id : '';

        return [
            'code' => ['required', 'string', 'max:100', 'alpha_dash:ascii', Rule::unique('project_resources', 'code')->where('devolution_project_id', $projectId)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', Rule::in(['human', 'equipment', 'material', 'service'])],
            'capacity_unit' => ['required', Rule::in(['hours', 'days', 'units'])],
            'capacity_per_day' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'cost_rate' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'available_from' => ['required', 'date'],
            'available_to' => ['required', 'date', 'after_or_equal:available_from'],
        ];
    }
}
