<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageWorkflows->value) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('workflow_definitions', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'module' => ['required', Rule::in(['citizen-feedback', 'e-learning', 'partner-coordination', 'dswg', 'project-management', 'departmental-performance', 'monitoring-evaluation', 'grievance-redress', 'data-repository', 'analytics', 'intergovernmental-relations', 'performance-assessment', 'travel-clearance', 'knowledge-management'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
