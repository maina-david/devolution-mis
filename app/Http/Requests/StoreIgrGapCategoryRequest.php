<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIgrGapCategoryRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/', 'unique:igr_gap_categories,code'], 'name' => ['required', 'string', 'max:150'], 'description' => ['required', 'string', 'min:20', 'max:5000'], 'default_severity' => ['required', 'in:low,medium,high,critical']];
    }
}
