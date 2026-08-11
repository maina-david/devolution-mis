<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TriageCitizenCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageCitizenCases->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['assigned_to' => ['required', 'uuid', 'exists:users,id'], 'assigned_organization_id' => ['nullable', 'uuid', 'exists:organizations,id'], 'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'], 'priority' => ['required', 'in:low,medium,high,critical'], 'is_sensitive' => ['required', 'boolean'], 'triage_note' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
