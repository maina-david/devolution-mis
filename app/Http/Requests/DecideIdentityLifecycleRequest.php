<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DecideIdentityLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::CertifyAccess->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['decision' => ['required', 'in:approve,reject'], 'rationale' => ['required', 'string', 'min:30', 'max:5000']];
    }

    /** @return array{decision:string, rationale:string} */
    public function decisionData(): array
    {
        return ['decision' => $this->string('decision')->toString(), 'rationale' => $this->string('rationale')->toString()];
    }
}
