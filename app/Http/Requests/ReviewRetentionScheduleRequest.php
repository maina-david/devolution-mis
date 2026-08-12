<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRetentionScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approved,rejected'],
            'decision_reason' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }

    /** @return array{decision: string, decision_reason: string} */
    public function reviewData(): array
    {
        return [
            'decision' => $this->string('decision')->toString(),
            'decision_reason' => $this->string('decision_reason')->toString(),
        ];
    }
}
