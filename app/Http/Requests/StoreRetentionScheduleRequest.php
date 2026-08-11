<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRetentionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', 'unique:retention_schedules,code'], 'record_class' => ['required', 'string', 'max:255'], 'trigger_event' => ['required', 'string', 'max:2000'], 'retention_months' => ['required', 'integer', 'min:1', 'max:1200'], 'disposition_action' => ['required', 'in:review_then_destroy,transfer_to_archive,permanent_preservation,anonymize'], 'legal_authority' => ['required', 'string', 'max:3000'], 'legal_hold_rule' => ['required', 'string', 'max:3000'], 'next_review_at' => ['required', 'date', 'after:today']];
    }
}
