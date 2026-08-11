<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIgrForumRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'max:100', 'unique:igr_forums,code'], 'name' => ['required', 'string', 'max:255'], 'forum_type' => ['required', 'in:summit,council,committee,technical'], 'mandate' => ['required', 'string', 'max:10000'], 'secretariat_user_id' => ['nullable', 'uuid', 'exists:users,id']];
    }
}
