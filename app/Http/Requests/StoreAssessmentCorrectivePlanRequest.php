<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentCorrectivePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitAssessment->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assessment_finding_id' => ['nullable', 'required_without:assessment_appeal_id', Rule::prohibitedIf($this->filled('assessment_appeal_id')), 'uuid', 'exists:assessment_findings,id'],
            'assessment_appeal_id' => ['nullable', 'required_without:assessment_finding_id', Rule::prohibitedIf($this->filled('assessment_finding_id')), 'uuid', 'exists:assessment_appeals,id'],
            'reference' => ['required', 'string', 'max:100', 'unique:assessment_corrective_plans,reference'],
            'title' => ['required', 'string', 'max:255'],
            'root_cause' => ['required', 'string', 'max:5000'],
            'expected_outcome' => ['required', 'string', 'max:5000'],
            'due_at' => ['required', 'date', 'after:today'],
            'actions' => ['required', 'array', 'min:1', 'max:20'],
            'actions.*.accountable_owner_id' => ['required', 'uuid', 'exists:users,id'],
            'actions.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'actions.*.title' => ['required', 'string', 'max:255'],
            'actions.*.description' => ['required', 'string', 'max:5000'],
            'actions.*.success_indicator' => ['required', 'string', 'max:500'],
            'actions.*.target' => ['required', 'string', 'max:500'],
            'actions.*.due_at' => ['required', 'date', 'after:today'],
        ];
    }

    /** @return array{assessment_finding_id: string|null, assessment_appeal_id: string|null, reference: string, title: string, root_cause: string, expected_outcome: string, due_at: string, actions: list<array{accountable_owner_id: string, code: string, title: string, description: string, success_indicator: string, target: string, due_at: string}>} */
    public function payload(): array
    {
        $actions = [];
        foreach ($this->array('actions') as $action) {
            if (! is_array($action)) {
                continue;
            }
            $actions[] = [
                'accountable_owner_id' => (string) ($action['accountable_owner_id'] ?? ''),
                'code' => (string) ($action['code'] ?? ''),
                'title' => (string) ($action['title'] ?? ''),
                'description' => (string) ($action['description'] ?? ''),
                'success_indicator' => (string) ($action['success_indicator'] ?? ''),
                'target' => (string) ($action['target'] ?? ''),
                'due_at' => (string) ($action['due_at'] ?? ''),
            ];
        }

        return [
            'assessment_finding_id' => $this->filled('assessment_finding_id') ? $this->string('assessment_finding_id')->toString() : null,
            'assessment_appeal_id' => $this->filled('assessment_appeal_id') ? $this->string('assessment_appeal_id')->toString() : null,
            'reference' => $this->string('reference')->toString(),
            'title' => $this->string('title')->toString(),
            'root_cause' => $this->string('root_cause')->toString(),
            'expected_outcome' => $this->string('expected_outcome')->toString(),
            'due_at' => $this->string('due_at')->toString(),
            'actions' => $actions,
        ];
    }
}
