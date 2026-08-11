<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ViewSupportDesk->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'transition' => ['required', 'in:start,request_information,provide_information,resolve,close,reopen'],
            'narrative' => ['required', 'string', 'min:10', 'max:10000'],
            'resolution_summary' => ['nullable', Rule::requiredIf($this->string('transition')->is('resolve')), 'string', 'min:20', 'max:10000'],
        ];
    }
}
