<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPartnerAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transition = $this->string('transition')->toString();

        return $transition === 'submit'
            ? $this->user()?->can(ProgrammePermission::ManagePartners->value) === true
            : $this->user()?->can(ProgrammePermission::ApprovePartnerAgreements->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transition' => ['required', Rule::in(['submit', 'approve', 'reject'])],
            'comment' => ['nullable', 'string', 'max:2000', Rule::requiredIf($this->string('transition')->toString() === 'reject')],
        ];
    }
}
