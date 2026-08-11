<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIgrForumMeetingRequest extends FormRequest
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
            'igr_forum_id' => ['required', 'uuid', 'exists:igr_forums,id'],
            'reference' => ['required', 'string', 'max:100', 'unique:igr_forum_meetings,reference'],
            'title' => ['required', 'string', 'max:255'],
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'venue' => ['required', 'string', 'max:255'],
            'chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'quorum_confirmed' => ['required', 'boolean'],
            'minutes_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
