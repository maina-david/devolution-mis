<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionUatFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->string('action')->toString() === 'resolve'
            ? ProgrammePermission::RecordUatEvidence
            : ProgrammePermission::ApproveRolloutReadiness;

        return $this->user()?->can($permission->value) === true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['resolve', 'verify', 'reopen'])],
            'resolution' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
