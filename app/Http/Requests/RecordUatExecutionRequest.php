<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordUatExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::RecordUatEvidence->value) === true;
    }

    public function rules(): array
    {
        $failed = in_array($this->string('outcome')->toString(), ['fail', 'blocked'], true);

        return [
            'county_id' => ['required', 'uuid', 'exists:counties,id'],
            'environment' => ['required', 'string', 'max:80'],
            'outcome' => ['required', Rule::in(['pass', 'fail', 'blocked'])],
            'actual_result' => ['required', 'string', 'min:20', 'max:5000'],
            'evidence_references' => ['required', 'array', 'min:1', 'max:20'],
            'evidence_references.*' => ['required', 'string', 'max:255'],
            'started_at' => ['required', 'date'],
            'completed_at' => ['required', 'date', 'after_or_equal:started_at', 'before_or_equal:now'],
            'finding_owner_id' => [Rule::requiredIf($failed), 'nullable', 'uuid', 'exists:users,id', Rule::notIn([$this->user()?->id])],
            'finding_severity' => [Rule::requiredIf($failed), 'nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'finding_title' => [Rule::requiredIf($failed), 'nullable', 'string', 'max:255'],
            'finding_description' => [Rule::requiredIf($failed), 'nullable', 'string', 'min:20', 'max:5000'],
            'finding_due_on' => [Rule::requiredIf($failed), 'nullable', 'date', 'after_or_equal:today'],
        ];
    }
}
