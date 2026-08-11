<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeOperationalAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageOperations->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }
}
