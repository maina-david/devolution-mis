<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDswgActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDswgActions->value) === true
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
            'dswg_decision_id' => ['nullable', 'uuid', 'exists:dswg_decisions,id'],
            'code' => ['required', 'string', 'max:100', 'unique:dswg_actions,code'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'accountable_user_id' => ['required', 'uuid', 'exists:users,id'],
            'accountable_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ];
    }
}
