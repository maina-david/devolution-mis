<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionDswgActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDswgActions->value) === true
            || $this->user()?->can(ProgrammePermission::VerifyDswgActions->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transition' => ['required', 'in:start,update_progress,submit_completion,verify,reject'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'progress_note' => ['nullable', 'string', 'max:10000'],
            'completion_evidence' => ['nullable', 'string', 'min:20', 'max:20000'],
            'comment' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
