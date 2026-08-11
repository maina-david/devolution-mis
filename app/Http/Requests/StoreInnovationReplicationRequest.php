<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInnovationReplicationRequest extends FormRequest
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
        return [
            'devolution_innovation_id' => ['required', 'uuid', 'exists:devolution_innovations,id'],
            'target_county_id' => ['required', 'uuid', 'exists:counties,id'],
            'accountable_user_id' => ['required', 'uuid', Rule::exists('users', 'id')->whereNull('access_revoked_at')],
            'adaptation_plan' => ['required', 'string', 'min:40', 'max:5000'],
            'success_measure' => ['required', 'string', 'max:255'],
            'baseline_value' => ['required', 'numeric'],
            'target_value' => ['required', 'numeric', 'different:baseline_value'],
            'starts_on' => ['required', 'date'],
            'target_completion_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
