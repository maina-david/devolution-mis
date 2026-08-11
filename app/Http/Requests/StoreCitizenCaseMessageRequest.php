<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCitizenCaseMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::RespondCitizenCases->value) === true || $this->user()?->can(ProgrammePermission::ManageCitizenCases->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:10000'], 'visibility' => ['required', 'in:public,internal'], 'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,txt,doc,docx', 'max:10240'], 'source_type' => ['nullable', 'required_with:attachment', 'in:scanned,born_digital']];
    }
}
