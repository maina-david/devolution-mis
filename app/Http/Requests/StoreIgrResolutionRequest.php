<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIgrResolutionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageIgrResolutions->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'igr_forum_id' => ['required', 'uuid', 'exists:igr_forums,id'], 'resolution_number' => ['required', 'string', 'max:100', 'unique:igr_resolutions,resolution_number'],
            'igr_forum_meeting_id' => ['nullable', 'uuid', 'exists:igr_forum_meetings,id'],
            'title' => ['required', 'string', 'max:255'], 'resolution_text' => ['required', 'string', 'max:20000'], 'resolved_on' => ['required', 'date', 'before_or_equal:today'],
            'due_on' => ['required', 'date', 'after:resolved_on'], 'priority' => ['required', 'in:low,medium,high,critical'],
            'assignments' => ['required', 'array', 'min:1', 'max:50'], 'assignments.*.user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'assignments.*.organization_id' => ['nullable', 'uuid', 'exists:organizations,id'], 'assignments.*.county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'assignments.*.responsibility_role' => ['required', 'in:lead,implementer,oversight,support'], 'assignments.*.is_lead' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $assignments = $this->array('assignments');
            $leadCount = 0;
            foreach ($assignments as $assignment) {
                if (! is_array($assignment) || empty($assignment['user_id']) && empty($assignment['organization_id'])) {
                    $validator->errors()->add('assignments', 'Each responsible party requires a user or organization.');
                }
                if (is_array($assignment) && filter_var($assignment['is_lead'] ?? false, FILTER_VALIDATE_BOOL)) {
                    $leadCount++;
                }
            }
            if ($leadCount !== 1) {
                $validator->errors()->add('assignments', 'Exactly one responsible party must be designated as lead.');
            }
        });
    }
}
