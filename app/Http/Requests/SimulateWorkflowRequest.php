<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageWorkflows->value) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            'started_by' => ['required', 'uuid', Rule::exists('users', 'id')->whereNull('access_revoked_at')->whereNull('deleted_at')],
            'initial_context' => ['present', 'array'],
            'steps' => ['present', 'array', 'max:100'],
            'steps.*' => ['array:transition_name,actor_id,context_changes,occurred_at'],
            'steps.*.transition_name' => ['required', 'string', 'max:80'],
            'steps.*.actor_id' => ['required', 'uuid', Rule::exists('users', 'id')->whereNull('access_revoked_at')->whereNull('deleted_at')],
            'steps.*.context_changes' => ['present', 'array'],
            'steps.*.occurred_at' => ['nullable', 'date'],
        ];
    }
}
