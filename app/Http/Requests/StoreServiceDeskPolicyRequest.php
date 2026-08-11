<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceDeskPolicyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ConfigureSupportDesk->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Z0-9-]+$/'],
            'name' => ['required', 'string', 'min:8', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'business_calendar_id' => ['required', 'uuid', Rule::exists('business_calendars', 'id')->where('status', 'published')],
            'categories' => ['required', 'array', 'min:1', 'max:20'],
            'categories.*' => ['required', 'array:code,name'],
            'categories.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'categories.*.name' => ['required', 'string', 'max:120'],
            'channels' => ['required', 'array', 'min:1', 'max:5'],
            'channels.*' => ['required', Rule::in(['web', 'email', 'phone', 'walk_in', 'training']), 'distinct'],
            'priority_targets' => ['required', 'array:low,medium,high,critical'],
            'priority_targets.low' => ['required', 'array:first_response,resolution,reminder'],
            'priority_targets.medium' => ['required', 'array:first_response,resolution,reminder'],
            'priority_targets.high' => ['required', 'array:first_response,resolution,reminder'],
            'priority_targets.critical' => ['required', 'array:first_response,resolution,reminder'],
            'priority_targets.*.first_response' => ['required', 'numeric', 'min:0.25', 'max:240'],
            'priority_targets.*.resolution' => ['required', 'numeric', 'min:0.5', 'max:1000', 'gt:priority_targets.*.first_response'],
            'priority_targets.*.reminder' => ['required', 'numeric', 'min:0.25', 'max:168'],
            'escalation_rules' => ['required', 'array', 'min:4', 'max:24'],
            'escalation_rules.*' => ['required', 'array:priority,stage,tier'],
            'escalation_rules.*.priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'escalation_rules.*.stage' => ['required', Rule::in(['first_response', 'resolution'])],
            'escalation_rules.*.tier' => ['required', 'integer', 'between:1,3'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'roster' => ['required', 'array', 'min:2', 'max:200'],
            'roster.*' => ['required', 'array:user_id,county_id,tier,duty_role,is_primary,starts_at,ends_at'],
            'roster.*.user_id' => ['required', 'uuid', 'exists:users,id', 'distinct'],
            'roster.*.county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'roster.*.tier' => ['required', 'integer', 'between:1,3'],
            'roster.*.duty_role' => ['required', Rule::in(['responder', 'specialist', 'manager'])],
            'roster.*.is_primary' => ['required', 'boolean'],
            'roster.*.starts_at' => ['required', 'date'],
            'roster.*.ends_at' => ['nullable', 'date', 'after:roster.*.starts_at'],
        ];
    }
}
