<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionProgrammeEvaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([
            ProgrammePermission::ManageIndicators->value,
            ProgrammePermission::VerifyIndicatorData->value,
        ]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:start,submit_review,approve,return'], 'comment' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
