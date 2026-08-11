<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\EvaluationFinding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluationFindingActionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canAny([ProgrammePermission::ManageIndicators->value, ProgrammePermission::SubmitIndicatorData->value]) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $finding = $this->route('finding');
        $findingId = $finding instanceof EvaluationFinding ? $finding->id : (string) $finding;

        return ['accountable_owner_id' => ['required', 'uuid', 'exists:users,id'], 'code' => ['required', 'string', 'max:50', Rule::unique('evaluation_finding_actions', 'code')->where('evaluation_finding_id', $findingId)], 'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'], 'success_indicator' => ['required', 'string', 'max:500'], 'target' => ['required', 'string', 'max:500'], 'due_at' => ['required', 'date', 'after:today'], 'weight_percentage' => ['required', 'numeric', 'gt:0', 'max:100']];
    }

    /** @return array{accountable_owner_id: string, code: string, title: string, description: string, success_indicator: string, target: string, due_at: string, weight_percentage: float} */
    public function payload(): array
    {
        return [
            'accountable_owner_id' => $this->string('accountable_owner_id')->toString(),
            'code' => $this->string('code')->toString(),
            'title' => $this->string('title')->toString(),
            'description' => $this->string('description')->toString(),
            'success_indicator' => $this->string('success_indicator')->toString(),
            'target' => $this->string('target')->toString(),
            'due_at' => $this->string('due_at')->toString(),
            'weight_percentage' => $this->float('weight_percentage'),
        ];
    }
}
