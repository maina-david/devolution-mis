<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcessingActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageDataGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'data_asset_id' => ['required', 'uuid', 'exists:data_assets,id'],
            'retention_schedule_id' => ['required', 'uuid', Rule::exists('retention_schedules', 'id')->where('status', 'approved')],
            'reference' => ['required', 'string', 'max:80', 'unique:processing_activities,reference'],
            'name' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'],
            'lawful_basis' => ['required', 'in:consent,contract,legal_obligation,vital_interests,public_task,legitimate_interests'],
            'lawful_basis_reference' => ['required', 'string', 'max:3000'],
            'controller_name' => ['required', 'string', 'max:255'],
            'processor_names' => ['nullable', 'string', 'max:3000'],
            'recipient_categories' => ['nullable', 'string', 'max:3000'],
            'processing_operations' => ['required', 'string', 'max:3000'],
            'automated_decision_making' => ['required', 'boolean'],
            'cross_border_transfer' => ['required', 'boolean'],
            'transfer_countries' => ['nullable', 'required_if:cross_border_transfer,1', 'string', 'max:1000'],
            'transfer_safeguards' => ['nullable', 'required_if:cross_border_transfer,1', 'string', 'max:5000'],
            'dpia_status' => ['required', 'in:not_required,required,in_progress,completed'],
            'dpia_reference' => ['nullable', 'required_if:dpia_status,completed', 'string', 'max:255'],
            'risk_summary' => ['required', 'string', 'max:5000'],
            'security_measures' => ['required', 'string', 'max:5000'],
            'next_review_at' => ['required', 'date', 'after:today'],
        ];
    }
}
