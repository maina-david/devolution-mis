<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\BusinessCalendar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkflowVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageWorkflows->value) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'configuration' => ['required', 'array:initial_state,states,transitions,rules,state_slas,terminal_states,start_permission,escalation_user_id,escalation_permission,business_calendar_id'],
            'configuration.initial_state' => ['required', 'string', 'max:80'],
            'configuration.states' => ['required', 'array', 'min:1'],
            'configuration.states.*' => ['required', 'string', 'max:80', 'distinct'],
            'configuration.transitions' => ['present', 'array'],
            'configuration.transitions.*' => ['array:name,from,to,permission,rules,separation_from,sla_hours,terminal'],
            'configuration.transitions.*.name' => ['required', 'string', 'max:80'],
            'configuration.transitions.*.from' => ['required', 'string', 'max:80'],
            'configuration.transitions.*.to' => ['required', 'string', 'max:80'],
            'configuration.transitions.*.permission' => ['nullable', 'string', 'max:120'],
            'configuration.transitions.*.sla_hours' => ['nullable', 'numeric', 'gt:0', 'max:8760'],
            'configuration.transitions.*.terminal' => ['nullable', 'boolean'],
            'configuration.transitions.*.separation_from' => ['nullable', 'array'],
            'configuration.transitions.*.separation_from.*' => ['string', 'max:80', 'distinct'],
            'configuration.transitions.*.rules' => ['nullable', 'array'],
            'configuration.transitions.*.rules.*' => ['array:field,operator,value'],
            'configuration.transitions.*.rules.*.field' => ['required', 'string', 'max:255'],
            'configuration.transitions.*.rules.*.operator' => ['required', Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'present', 'absent'])],
            'configuration.rules' => ['present', 'array'],
            'configuration.rules.*' => ['array:field,operator,value'],
            'configuration.rules.*.field' => ['required', 'string', 'max:255'],
            'configuration.rules.*.operator' => ['required', Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'present', 'absent'])],
            'configuration.state_slas' => ['nullable', 'array'],
            'configuration.state_slas.*' => ['numeric', 'gt:0', 'max:8760'],
            'configuration.terminal_states' => ['nullable', 'array'],
            'configuration.terminal_states.*' => ['string', 'max:80', 'distinct'],
            'configuration.start_permission' => ['nullable', 'string', 'max:120'],
            'configuration.escalation_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
            'configuration.escalation_permission' => ['nullable', 'string', 'max:120'],
            'configuration.business_calendar_id' => ['nullable', 'uuid', Rule::exists((new BusinessCalendar)->getTable(), 'id')->where('status', 'published')->whereNull('deleted_at')],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $states = $this->array('configuration')['states'] ?? [];
            $initialState = $this->input('configuration.initial_state');

            if (is_string($initialState) && ! in_array($initialState, $states, true)) {
                $validator->errors()->add('configuration.initial_state', 'The initial state must be one of the declared states.');
            }

            foreach ($this->input('configuration.transitions', []) as $index => $transition) {
                if (! is_array($transition)) {
                    continue;
                }

                foreach (['from', 'to'] as $endpoint) {
                    if (isset($transition[$endpoint]) && ! in_array($transition[$endpoint], $states, true)) {
                        $validator->errors()->add("configuration.transitions.{$index}.{$endpoint}", "The {$endpoint} state must be declared.");
                    }
                }
            }

            foreach ($this->input('configuration.terminal_states', []) as $index => $terminalState) {
                if (! in_array($terminalState, $states, true)) {
                    $validator->errors()->add("configuration.terminal_states.{$index}", 'Every terminal state must be declared.');
                }
            }

            $transitionKeys = [];
            $transitions = $this->input('configuration.transitions', []);
            foreach (is_array($transitions) ? $transitions : [] as $transition) {
                if (is_array($transition)) {
                    $transitionKeys[] = ($transition['from'] ?? '').':'.($transition['name'] ?? '');
                }
            }

            if (count($transitionKeys) !== count(array_unique($transitionKeys))) {
                $validator->errors()->add('configuration.transitions', 'Transition names must be unique within each source state.');
            }
        }];
    }
}
