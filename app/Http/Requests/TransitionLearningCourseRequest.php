<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionLearningCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::ManageLearning->value, ProgrammePermission::ReviewLearning->value]) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:submit_review,publish,return,retire'], 'rationale' => ['required', 'string', 'max:5000']];
    }
}
