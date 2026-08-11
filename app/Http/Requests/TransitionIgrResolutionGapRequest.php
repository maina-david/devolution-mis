<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionIgrResolutionGapRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::UpdateIgrResolutions->value) === true || $this->user()?->can(ProgrammePermission::ManageIgrResolutions->value) === true || $this->user()?->can(ProgrammePermission::CloseIgrResolutions->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:start_mitigation,resolve,accept,reject'], 'rationale' => ['required', 'string', 'min:20', 'max:10000']];
    }
}
