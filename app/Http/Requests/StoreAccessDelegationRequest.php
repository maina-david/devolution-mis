<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccessDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'beneficiary_id' => ['required', 'uuid', 'exists:users,id'],
            'access_type' => ['required', 'in:delegated,emergency'],
            'scope_type' => ['required', 'in:county_portfolio,national'],
            'permission_scope' => ['required', 'array', 'min:1', 'max:20'],
            'permission_scope.*' => ['required', 'string', 'distinct', Rule::in(array_column(ProgrammePermission::cases(), 'value'))],
            'county_ids' => ['nullable', 'required_if:scope_type,county_portfolio', 'array', 'min:1', 'max:47'],
            'county_ids.*' => ['uuid', 'distinct', 'exists:counties,id'],
            'business_justification' => ['required', 'string', 'min:30', 'max:5000'],
            'incident_reference' => ['nullable', 'required_if:access_type,emergency', 'string', 'max:255'],
            'compensating_controls' => ['nullable', 'required_if:access_type,emergency', 'string', 'min:20', 'max:5000'],
            'starts_at' => ['required', 'date', 'after_or_equal:now'],
            'expires_at' => ['required', 'date', 'after:starts_at'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->string('access_type')->is('emergency') && $this->date('starts_at')?->diffInMinutes($this->date('expires_at')) > 240) {
                $validator->errors()->add('expires_at', 'Emergency access is limited to four hours.');
            }
        }];
    }
}
