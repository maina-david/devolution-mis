<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIgrResolutionDependencyRequest extends FormRequest
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
            'prerequisite_resolution_id' => ['required', 'uuid', 'exists:igr_resolutions,id'],
            'dependency_type' => ['required', 'in:blocks,informs'],
            'rationale' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
