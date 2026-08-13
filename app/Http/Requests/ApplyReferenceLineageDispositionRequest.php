<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class ApplyReferenceLineageDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageOperations->value) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}
