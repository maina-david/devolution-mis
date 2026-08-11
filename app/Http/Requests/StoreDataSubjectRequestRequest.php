<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDataSubjectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['assigned_to' => ['required', 'uuid', 'exists:users,id'], 'request_type' => ['required', 'in:access,rectification,erasure,restriction,objection,portability'], 'requester_name' => ['required', 'string', 'max:255'], 'requester_contact' => ['required', 'string', 'max:500'], 'contact_channel' => ['required', 'in:email,phone,letter,in_person'], 'scope' => ['required', 'string', 'min:20', 'max:5000'], 'received_at' => ['required', 'date', 'before_or_equal:now']];
    }
}
