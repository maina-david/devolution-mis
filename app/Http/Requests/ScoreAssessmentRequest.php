<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class ScoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ScoreAssessment->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['score' => ['required', 'numeric', 'between:0,100']];
    }
}
