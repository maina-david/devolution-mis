<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectBudgetLineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageProjects->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
            'approved_amount' => ['required', 'numeric', 'min:0'],
            'committed_amount' => ['required', 'numeric', 'min:0'],
            'actual_amount' => ['required', 'numeric', 'min:0'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'amendment_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
