<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Services\LegacyReferenceInventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferenceLineageDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageReferenceData->value) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'record_type' => ['required', Rule::in(LegacyReferenceInventory::TYPE_KEYS)],
            'record_id' => ['required', 'uuid'],
            'decision' => ['required', Rule::in(['pin_release', 'retain_legacy', 'deprecate'])],
            'reference_data_release_id' => [Rule::requiredIf($this->input('decision') === 'pin_release'), 'nullable', 'uuid', 'exists:reference_data_releases,id'],
            'successor_record_type' => ['nullable', 'required_with:successor_record_id', Rule::in(LegacyReferenceInventory::TYPE_KEYS)],
            'successor_record_id' => ['nullable', 'required_with:successor_record_type', 'uuid'],
            'business_reason' => ['required', 'string', 'min:20', 'max:3000'],
            'source_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
