<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkArchiveCountiesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => [Rule::requiredIf($this->route('county') === null), 'array', 'min:1', 'max:47'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_values($this->validated('ids'));
    }
}
