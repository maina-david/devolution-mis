<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDswgCollaborationThreadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ParticipateDswg->value) === true
            || $this->user()?->can(ProgrammePermission::ManageDswg->value) === true;
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
            'title' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:10000'],
        ];
    }
}
