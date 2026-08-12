<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicCitizenCaseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_anonymous' => $this->boolean('is_anonymous')]);
    }

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
        return ['case_type' => ['required', 'in:feedback,grievance'], 'category' => ['required', 'in:complaint,suggestion,compliment,inquiry,grievance'], 'channel' => ['required', 'in:web,mobile,sms,ussd,social,hotline,in_person,api'], 'county_id' => ['required', 'uuid', 'exists:counties,id'], 'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'], 'subject' => ['required', 'string', 'min:5', 'max:255'], 'description' => ['required', 'string', 'min:20', 'max:15000'], 'is_anonymous' => ['required', 'boolean'], 'citizen_name' => ['nullable', 'required_if:is_anonymous,0', 'string', 'max:255'], 'citizen_email' => ['nullable', 'email:rfc', 'max:255'], 'citizen_phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{9,16}$/'], 'preferred_contact' => ['required', 'in:none,email,sms,phone'], 'accessibility_needs' => ['nullable', 'string', 'max:2000'], 'consent_given' => ['accepted'], 'privacy_notice_version' => ['required', Rule::in([(string) config('privacy.public_notice.version')])], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,txt,doc,docx', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp,text/plain,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'max:10240'], 'source_type' => ['nullable', 'required_with:attachment', 'in:scanned,born_digital'], 'website' => ['nullable', 'max:0']];
    }
}
