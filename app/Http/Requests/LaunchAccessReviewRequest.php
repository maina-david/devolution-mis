<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LaunchAccessReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageSecurityGovernance->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['reviewer_id' => ['required', 'uuid', 'exists:users,id'], 'reference' => ['required', 'string', 'max:80', 'unique:access_review_campaigns,reference'], 'name' => ['required', 'string', 'max:255'], 'scope' => ['required', 'string', 'min:20', 'max:5000'], 'role_scope' => ['required', 'array', 'min:1'], 'role_scope.*' => ['required', 'in:county-official,county-admin,assessor,development-partner,top-management,devolution-admin,platform-admin'], 'period_from' => ['required', 'date'], 'period_to' => ['required', 'date', 'after_or_equal:period_from', 'before_or_equal:today'], 'due_at' => ['required', 'date', 'after:now']];
    }
}
