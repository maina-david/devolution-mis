<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LearningAnalyticsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ViewLearning->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'course_id' => ['nullable', 'uuid', 'exists:learning_courses,id'],
            'status' => ['nullable', 'string', 'in:enrolled,in_progress,completed,withdrawn'],
            'search' => ['nullable', 'string', 'max:120'],
            'course_page' => ['nullable', 'integer', 'min:1'],
            'county_page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
        ];
    }
}
