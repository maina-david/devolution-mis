<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Support\ReferenceCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageChangeReadiness->value) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['rollout_wave_id' => ['required', 'uuid', 'exists:rollout_waves,id'], 'county_id' => ['nullable', 'uuid', 'exists:counties,id'], 'facilitator_id' => ['nullable', 'uuid', 'exists:users,id'], 'code' => ['required', 'string', 'max:50', Rule::unique('training_cohorts', 'code')], 'name' => ['required', 'string', 'max:200'], 'audience_role' => ['required', Rule::enum(UserRole::class)], 'delivery_mode' => ['required', Rule::in(['in_person', 'virtual', 'blended'])], 'language' => ['required', 'string', 'size:2', Rule::in(ReferenceCatalogue::languages())], 'venue' => ['nullable', 'string', 'max:300'], 'seat_capacity' => ['required', 'integer', 'min:1', 'max:500'], 'minimum_attendance_hours' => ['required', 'numeric', 'min:0.5', 'max:100'], 'passing_score' => ['required', 'numeric', 'min:1', 'max:100'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']];
    }
}
