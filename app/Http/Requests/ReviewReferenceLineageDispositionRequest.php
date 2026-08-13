<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewReferenceLineageDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ApproveReferenceData->value) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => ['required', 'string', 'min:20', 'max:3000'],
        ];
    }
}
