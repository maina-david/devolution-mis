<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicDataSubjectRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_type' => ['required', 'in:access,rectification,erasure,restriction,objection,portability'],
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_contact' => [
                'required',
                'string',
                'max:500',
                Rule::when($this->input('contact_channel') === 'email', ['email:rfc']),
                Rule::when($this->input('contact_channel') === 'phone', ['regex:/^\+?[0-9 ]{9,16}$/']),
            ],
            'contact_channel' => ['required', 'in:email,phone,letter'],
            'scope' => ['required', 'string', 'min:20', 'max:5000'],
            'consent_given' => ['accepted'],
            'privacy_notice_version' => ['required', Rule::in([(string) config('privacy.public_notice.version')])],
            'website' => ['nullable', 'max:0'],
        ];
    }
}
