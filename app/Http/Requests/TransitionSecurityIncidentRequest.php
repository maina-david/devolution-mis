<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\SecurityIncident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSecurityIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $incident = $this->route('securityIncident');

        return [
            'transition' => ['required', 'in:acknowledge,contain,eradicate,recover,close'],
            'narrative' => ['required', 'string', 'min:20', 'max:10000'],
            'evidence_reference' => ['nullable', Rule::requiredIf(fn (): bool => in_array($this->string('transition')->toString(), ['eradicate', 'recover'], true)), 'string', 'max:255'],
            'root_cause' => ['nullable', 'required_if:transition,close', 'string', 'min:20', 'max:10000'],
            'corrective_actions' => ['nullable', 'required_if:transition,close', 'string', 'min:20', 'max:10000'],
            'lessons_learned' => ['nullable', 'required_if:transition,close', 'string', 'min:20', 'max:10000'],
            'exercise_outcome' => ['nullable', Rule::requiredIf(fn (): bool => $this->string('transition')->is('close') && $incident instanceof SecurityIncident && $incident->record_type === 'exercise'), 'in:effective,partially_effective,ineffective'],
            'next_exercise_due_at' => ['nullable', Rule::requiredIf(fn (): bool => $this->string('transition')->is('close') && $incident instanceof SecurityIncident && $incident->record_type === 'exercise'), 'date', 'after:now'],
        ];
    }
}
