<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransitionCitizenCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::RespondCitizenCases->value) === true || $this->user()?->can(ProgrammePermission::ResolveCitizenCases->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['transition' => ['required', 'in:start,escalate,resume,submit_resolution,approve_resolution,reject_resolution,close'], 'resolution_summary' => ['nullable', 'required_if:transition,submit_resolution', 'string', 'min:20', 'max:10000'], 'comment' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
