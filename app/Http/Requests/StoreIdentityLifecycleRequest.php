<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIdentityLifecycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'source_system' => ['required', 'string', 'max:100'],
            'source_event_id' => ['required', 'string', 'max:255', Rule::unique('identity_lifecycle_requests')->where('source_system', $this->string('source_system')->toString())],
            'source_evidence_reference' => ['required', 'string', 'max:500'],
            'event_type' => ['required', Rule::in(['joiner', 'mover', 'leaver'])],
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'effective_at' => ['required', 'date'],
            'proposed_role' => ['nullable', 'required_unless:event_type,leaver', Rule::enum(UserRole::class)],
            'proposed_home_county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'proposed_assigned_county_ids' => ['nullable', 'array', 'max:47'],
            'proposed_assigned_county_ids.*' => ['uuid', 'distinct', 'exists:counties,id'],
            'business_reason' => ['required', 'string', 'min:30', 'max:5000'],
        ];
    }

    /** @return array{source_system:string, source_event_id:string, source_evidence_reference:string, event_type:string, user_id:string, effective_at:string, proposed_role?:string|null, proposed_home_county_id?:string|null, proposed_assigned_county_ids?:list<string>, business_reason:string} */
    public function lifecycleData(): array
    {
        return [
            'source_system' => $this->string('source_system')->toString(),
            'source_event_id' => $this->string('source_event_id')->toString(),
            'source_evidence_reference' => $this->string('source_evidence_reference')->toString(),
            'event_type' => $this->string('event_type')->toString(),
            'user_id' => $this->string('user_id')->toString(),
            'effective_at' => $this->string('effective_at')->toString(),
            'proposed_role' => $this->filled('proposed_role') ? $this->string('proposed_role')->toString() : null,
            'proposed_home_county_id' => $this->filled('proposed_home_county_id') ? $this->string('proposed_home_county_id')->toString() : null,
            'proposed_assigned_county_ids' => array_values(array_filter($this->array('proposed_assigned_county_ids'), is_string(...))),
            'business_reason' => $this->string('business_reason')->toString(),
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->string('event_type')->toString() === 'leaver') {
                return;
            }

            $role = UserRole::tryFrom($this->string('proposed_role')->toString());
            if (in_array($role, [UserRole::CountyOfficial, UserRole::CountyAdmin], true) && ! $this->filled('proposed_home_county_id')) {
                $validator->errors()->add('proposed_home_county_id', 'County roles require a home county.');
            }
            if ($role?->hasAssignedCountyScope() && $this->array('proposed_assigned_county_ids') === []) {
                $validator->errors()->add('proposed_assigned_county_ids', 'Portfolio roles require at least one assigned county.');
            }
        }];
    }
}
