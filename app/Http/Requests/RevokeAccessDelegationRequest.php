<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RevokeAccessDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::ManageSecurityGovernance->value, ProgrammePermission::CertifyAccess->value]) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['revocation_reason' => ['required', 'string', 'min:20', 'max:5000']];
    }
}
