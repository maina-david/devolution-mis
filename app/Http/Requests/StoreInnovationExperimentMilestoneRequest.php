<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInnovationExperimentMilestoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageKnowledge->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['owner_id' => ['required', 'uuid', 'exists:users,id'], 'title' => ['required', 'string', 'max:255'], 'hypothesis' => ['required', 'string', 'min:20', 'max:5000'], 'success_metric' => ['required', 'string', 'max:255'], 'baseline_value' => ['required', 'string', 'max:255'], 'target_value' => ['required', 'string', 'max:255'], 'due_at' => ['required', 'date', 'after:today']];
    }
}
