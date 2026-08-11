<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReleaseRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageOperations->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['version' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._+-]*$/'], 'git_sha' => ['required', 'string', 'regex:/^[a-f0-9]{40}$/'], 'environment' => ['required', 'in:test,staging,pilot,production'], 'artifact_checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'change_reference' => ['required', 'string', 'max:255'], 'migration_batch' => ['nullable', 'integer', 'min:0'], 'deployed_at' => ['required', 'date', 'before_or_equal:now'], 'notes' => ['nullable', 'string', 'max:5000']];
    }
}
