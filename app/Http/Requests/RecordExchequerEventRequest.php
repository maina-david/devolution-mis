<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExchequerEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageIntegrations->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['event_type' => ['required', Rule::in(['submitted_to_treasury', 'treasury_forwarded_ocob', 'ocob_authorized', 'treasury_issued_cbk', 'cbk_credited', 'returned', 'exception'])], 'source_system' => ['required', Rule::in(['IDMIS', 'TREASURY', 'OCOB', 'CBK'])], 'source_event_reference' => ['required', 'string', 'max:255', Rule::unique('exchequer_events')->where('source_system', $this->string('source_system')->toString())], 'occurred_at' => ['required', 'date', 'before_or_equal:now'], 'integration_exchange_id' => ['nullable', 'uuid', 'exists:integration_exchanges,id'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
