<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class EnrollTrainingParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageChangeReadiness->value) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['training_cohort_id' => ['required', 'uuid', 'exists:training_cohorts,id'], 'user_id' => ['nullable', 'uuid', 'exists:users,id'], 'county_id' => ['nullable', 'uuid', 'exists:counties,id'], 'participant_reference' => ['required', 'string', 'max:100'], 'role_title' => ['required', 'string', 'max:150']];
    }
}
