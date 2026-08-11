<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ConfigurePlatform->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['value' => ['required', 'string', 'max:2000']];
    }
}
