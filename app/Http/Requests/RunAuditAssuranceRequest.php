<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RunAuditAssuranceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can(ProgrammePermission::ManageSecurityGovernance->value) && $user->programmeRole()->hasNationalScope();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
