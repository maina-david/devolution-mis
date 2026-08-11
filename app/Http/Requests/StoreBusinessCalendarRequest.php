<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageWorkflows->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9-]+$/'], 'name' => ['required', 'string', 'max:255'], 'timezone' => ['required', 'string', Rule::in(ReferenceCatalogue::timezones())], 'working_days' => ['required', 'array', 'min:1', 'max:7'], 'working_days.*' => ['required', 'integer', 'between:1,7', 'distinct'], 'workday_starts_at' => ['required', 'date_format:H:i'], 'workday_ends_at' => ['required', 'date_format:H:i', 'after:workday_starts_at'], 'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after:effective_from']];
    }
}
