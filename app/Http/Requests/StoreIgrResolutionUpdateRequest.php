<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIgrResolutionUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::UpdateIgrResolutions->value) === true || $this->user()?->can(ProgrammePermission::ManageIgrResolutions->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['progress_percentage' => ['required', 'integer', 'min:0', 'max:100'], 'narrative' => ['required', 'string', 'min:20', 'max:10000'], 'implementation_gap' => ['nullable', 'string', 'max:10000'], 'evidence_reference' => ['nullable', 'string', 'min:10', 'max:20000']];
    }
}
