<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideUatCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ApproveRolloutReadiness->value) === true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'decision_reason' => ['required', 'string', 'min:30', 'max:5000'],
        ];
    }
}
