<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::SubmitSupportTickets->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'category' => ['required', 'in:access,incident,service_request,data_quality,integration,training,document,other'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'channel' => ['required', 'in:web,email,phone,walk_in,training'],
            'subject' => ['required', 'string', 'min:8', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:20000'],
        ];
    }
}
