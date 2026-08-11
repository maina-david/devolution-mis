<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIntegrationContractRequest extends FormRequest
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
        return ['integration_system_id' => ['required', 'uuid', 'exists:integration_systems,id'], 'name' => ['required', 'string', 'max:255'], 'resource_name' => ['required', 'string', 'max:255'], 'http_method' => ['required', 'in:GET,POST,PUT,PATCH'], 'path' => ['required', 'string', 'max:1000', 'starts_with:/'], 'request_schema' => ['required', 'array'], 'response_schema' => ['nullable', 'array'], 'field_mappings' => ['nullable', 'array'], 'required_headers' => ['nullable', 'array'], 'idempotency_field' => ['nullable', 'string', 'max:255'], 'retry_policy' => ['required', 'array'], 'retry_policy.max_attempts' => ['required', 'integer', 'between:1,10'], 'retry_policy.backoff_seconds' => ['required', 'array', 'min:1', 'max:10'], 'retry_policy.backoff_seconds.*' => ['integer', 'min:1', 'max:86400'], 'rate_limit_per_minute' => ['required', 'integer', 'between:1,10000']];
    }

    protected function prepareForValidation(): void
    {
        foreach (['request_schema', 'response_schema', 'field_mappings', 'required_headers', 'retry_policy'] as $field) {
            if (is_string($this->input($field))) {
                $decoded = json_decode($this->input($field), true);
                if (is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }
}
