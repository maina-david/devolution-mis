<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReviewEmergencyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::CertifyAccess->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['post_use_outcome' => ['required', 'in:appropriate,exception_noted,investigation_required'], 'post_use_findings' => ['required', 'string', 'min:30', 'max:10000']];
    }
}
