<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageAssessmentConfiguration->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'county_id' => ['required', 'uuid', 'exists:counties,id'],
            'assessment_cycle_id' => [
                'required',
                'uuid',
                Rule::exists('assessment_cycles', 'id')->whereIn('status', ['planned', 'open'])->whereNull('deleted_at'),
            ],
        ];
    }
}
