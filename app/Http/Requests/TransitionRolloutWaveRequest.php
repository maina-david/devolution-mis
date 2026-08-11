<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;

class TransitionRolloutWaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ApproveRolloutReadiness->value) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['readiness_notes' => ['required', 'string', 'min:30', 'max:3000']];
    }
}
