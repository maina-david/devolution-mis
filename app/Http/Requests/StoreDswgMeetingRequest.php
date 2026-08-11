<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDswgMeetingRequest extends FormRequest
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
            'reference' => ['required', 'string', 'max:100', 'unique:dswg_meetings,reference'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'meeting_mode' => ['required', 'in:physical,virtual,hybrid'],
            'venue' => ['nullable', 'required_if:meeting_mode,physical,hybrid', 'string', 'max:500'],
            'virtual_link' => ['nullable', 'required_if:meeting_mode,virtual,hybrid', 'url', 'max:1000'],
            'agenda' => ['required', 'string', 'max:20000'],
            'quorum_required' => ['required', 'integer', 'min:1', 'max:500'],
            'invitee_ids' => ['required', 'array', 'min:1'],
            'invitee_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
        ];
    }
}
