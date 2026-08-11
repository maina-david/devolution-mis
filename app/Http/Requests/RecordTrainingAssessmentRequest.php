<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordTrainingAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::RecordTrainingEvidence->value) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['assessment_type' => ['required', Rule::in(['pre_training', 'post_training', 'practical'])], 'score' => ['required', 'numeric', 'min:0', 'max:100'], 'attended_hours' => ['required', 'numeric', 'min:0', 'max:100'], 'feedback' => ['required', 'string', 'min:20', 'max:3000'], 'evidence_references' => ['required', 'array', 'min:1'], 'evidence_references.*' => ['string', 'max:300']];
    }
}
