<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePerformancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitPerformancePlans->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'performance_cycle_id' => ['required', 'uuid', 'exists:performance_cycles,id'],
            'supervisor_id' => ['required', 'uuid', 'exists:users,id', 'different:employee_id'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'plan_type' => ['required', 'in:individual,departmental'],
            'hris_employee_reference' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'overall_expectations' => ['required', 'string', 'min:20', 'max:10000'],
            'goals' => ['required', 'array', 'min:1', 'max:20'],
            'goals.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'goals.*.title' => ['required', 'string', 'max:255'],
            'goals.*.description' => ['required', 'string', 'max:5000'],
            'goals.*.kpi' => ['required', 'string', 'max:255'],
            'goals.*.unit_of_measure' => ['required', 'string', 'max:100'],
            'goals.*.baseline_value' => ['nullable', 'numeric'],
            'goals.*.target_value' => ['required', 'numeric'],
            'goals.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $weight = collect($this->array('goals'))->filter(fn (mixed $goal): bool => is_array($goal))->sum(fn (array $goal): float => (float) ($goal['weight'] ?? 0));
            if (abs($weight - 100) > 0.01) {
                $validator->errors()->add('goals', 'Goal weights must total exactly 100 percent.');
            }
        }];
    }
}
