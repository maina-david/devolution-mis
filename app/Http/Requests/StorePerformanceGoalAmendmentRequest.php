<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceGoalAmendmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitPerformancePlans->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'kpi' => ['required', 'string', 'max:255'],
            'unit_of_measure' => ['required', 'string', 'max:100'],
            'baseline_value' => ['nullable', 'numeric'],
            'target_value' => ['required', 'numeric'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'reason' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
