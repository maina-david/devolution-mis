<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDswgMeetingSeriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDswg->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dswg_working_group_id' => ['required', 'uuid', 'exists:dswg_working_groups,id'],
            'reference_prefix' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9][A-Z0-9\/-]*$/', 'unique:dswg_meeting_series,reference_prefix'],
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(['weekly', 'monthly', 'quarterly'])],
            'interval' => ['required', 'integer', 'min:1', 'max:12'],
            'first_starts_at' => ['required', 'date', 'after:now'],
            'ends_on' => ['required', 'date', 'after_or_equal:first_starts_at'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:1440'],
            'timezone' => ['required', 'string', Rule::in(ReferenceCatalogue::timezones())],
            'meeting_mode' => ['required', Rule::in(['physical', 'virtual', 'hybrid'])],
            'venue' => ['nullable', 'required_if:meeting_mode,physical,hybrid', 'string', 'max:500'],
            'virtual_link' => ['nullable', 'required_if:meeting_mode,virtual,hybrid', 'url', 'max:1000'],
            'agenda' => ['required', 'string', 'max:20000'],
            'quorum_required' => ['required', 'integer', 'min:1', 'max:500'],
            'generation_horizon_days' => ['required', 'integer', 'min:7', 'max:365'],
            'invitee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'invitee_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
        ];
    }
}
