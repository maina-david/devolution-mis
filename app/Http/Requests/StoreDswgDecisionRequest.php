<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDswgDecisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDswg->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:dswg_decisions,code'],
            'title' => ['required', 'string', 'max:255'],
            'decision_text' => ['required', 'string', 'max:20000'],
            'decision_type' => ['required', 'in:resolution,recommendation,endorsement,deferral'],
            'decided_at' => ['required', 'date'],
        ];
    }
}
