<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningCohortRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageLearning->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['learning_course_id' => ['required', 'uuid', 'exists:learning_courses,id'], 'instructor_id' => ['required', 'uuid', 'exists:users,id'], 'county_id' => ['nullable', 'uuid', 'exists:counties,id'], 'code' => ['required', 'string', 'max:80', 'alpha_dash:ascii', Rule::unique('learning_cohorts', 'code')], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'capacity' => ['required', 'integer', 'min:1', 'max:5000'], 'enrollment_opens_on' => ['required', 'date'], 'enrollment_closes_on' => ['required', 'date', 'after_or_equal:enrollment_opens_on'], 'starts_at' => ['required', 'date', 'after:enrollment_closes_on'], 'ends_at' => ['required', 'date', 'after:starts_at']];
    }
}
