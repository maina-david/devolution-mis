<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRolloutWaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageChangeReadiness->value) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:50', Rule::unique('rollout_waves', 'code')], 'name' => ['required', 'string', 'max:200'], 'objective' => ['required', 'string', 'max:3000'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'], 'planned_participants' => ['required', 'integer', 'min:1', 'max:5000'], 'county_ids' => ['required', 'array', 'min:1'], 'county_ids.*' => ['uuid', 'distinct', 'exists:counties,id'], 'entry_criteria' => ['required', 'array', 'min:1'], 'entry_criteria.*' => ['string', 'max:300'], 'support_channels' => ['required', 'array', 'min:1'], 'support_channels.*' => ['string', 'max:100'], 'help_desk_rehearsed' => ['required', 'boolean'], 'training_materials_approved' => ['required', 'boolean']];
    }
}
