<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationFindingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageIndicators->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reference' => ['required', 'string', 'max:80', 'unique:evaluation_findings,reference'], 'title' => ['required', 'string', 'max:255'], 'finding' => ['required', 'string', 'max:10000'], 'recommendation' => ['required', 'string', 'max:10000'], 'severity' => ['required', Rule::in(['low', 'moderate', 'high', 'critical'])], 'accountable_owner_id' => ['required', 'uuid', 'exists:users,id'], 'due_at' => ['required', 'date', 'after:today']];
    }
}
