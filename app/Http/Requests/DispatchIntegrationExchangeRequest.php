<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DispatchIntegrationExchangeRequest extends FormRequest
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
        return ['county_id' => ['nullable', 'uuid', 'exists:counties,id'], 'external_reference' => ['nullable', 'string', 'max:255'], 'idempotency_key' => ['required', 'string', 'max:255'], 'payload' => ['required', 'array'], 'source_occurred_at' => ['nullable', 'date']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('payload'))) {
            $decoded = json_decode($this->input('payload'), true);
            if (is_array($decoded)) {
                $this->merge(['payload' => $decoded]);
            }
        }
    }
}
