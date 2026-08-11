<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerCollaborationActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManagePartners->value) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['county_id' => ['required', 'uuid', 'exists:counties,id'], 'code' => ['required', 'string', 'max:50'], 'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'min:20', 'max:5000'], 'accountable_user_id' => ['required', 'uuid', 'exists:users,id'], 'accountable_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'], 'due_on' => ['required', 'date']];
    }
}
