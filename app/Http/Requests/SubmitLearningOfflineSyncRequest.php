<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use JsonException;

class SubmitLearningOfflineSyncRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $file = $this->file('sync_file');
        if ($file === null || ! $file->isValid()) {
            return;
        }

        try {
            $payload = json_decode($file->getContent(), true, 32, JSON_THROW_ON_ERROR);
            $this->merge(['payload' => $payload]);
        } catch (JsonException) {
            $this->merge(['payload' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::EnrollLearning->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sync_file' => ['required', 'file', 'extensions:json', 'mimetypes:application/json,text/plain', 'max:1024'],
            'payload' => ['required', 'array'],
            'payload.schema' => ['required', Rule::in(['idmis.learning-offline-progress.v1'])],
            'payload.client_sync_id' => ['required', 'uuid'],
            'payload.device_id' => ['required', 'uuid'],
            'payload.package_id' => ['required', 'uuid'],
            'payload.package_manifest_checksum' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'payload.exported_at' => ['required', 'date', 'before_or_equal:now'],
            'payload.events' => ['required', 'array', 'min:1', 'max:100'],
            'payload.events.*.client_event_id' => ['required', 'uuid', 'distinct:strict'],
            'payload.events.*.lesson_id' => ['required', 'uuid', 'distinct:strict'],
            'payload.events.*.status' => ['required', Rule::in(['in_progress', 'completed'])],
            'payload.events.*.progress_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'payload.events.*.time_spent_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
            'payload.events.*.occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'payload.events.*.state' => ['nullable', 'array', 'max:25'],
        ];
    }

    public function messages(): array
    {
        return ['payload.required' => 'The selected file is not valid JSON.', 'payload.schema.in' => 'The offline progress schema is not supported.'];
    }

    /** @return array<string, mixed> */
    public function syncPayload(): array
    {
        $payload = $this->validated('payload');

        return is_array($payload) ? $payload : [];
    }
}
