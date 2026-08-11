<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeDiscussionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ContributeKnowledge->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['knowledge_item_id' => ['nullable', 'uuid', 'exists:knowledge_items,id'], 'county_id' => ['nullable', 'uuid', 'exists:counties,id'], 'title' => ['required', 'string', 'max:255'], 'prompt' => ['required', 'string', 'max:5000'], 'visibility' => ['required', 'in:national,county,internal']];
    }
}
