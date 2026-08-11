<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Support\ReferenceCatalogue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreKnowledgeItemRequest extends FormRequest
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
        return [
            'assessment_document_id' => ['nullable', 'uuid', 'exists:assessment_documents,id'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'],
            'item_type' => ['required', 'in:best_practice,case_study,research,publication,toolkit,blog'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:1500'],
            'content_body' => ['nullable', 'string', 'max:50000'],
            'tags' => ['required'],
            'visibility' => ['required', 'in:national,county,internal'],
            'source_organization' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'url', 'max:2000'],
            'language' => ['required', 'string', 'size:2', Rule::in(ReferenceCatalogue::languages())],
            'course_ids' => ['nullable', 'array', 'max:20'],
            'course_ids.*' => ['uuid', 'exists:learning_courses,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('content_body') && ! $this->filled('external_url') && ! $this->filled('assessment_document_id')) {
                $validator->errors()->add('content_body', 'Provide authored content, an external source, or a repository document.');
            }
            if ($this->input('visibility') === 'county' && ! $this->filled('county_id')) {
                $validator->errors()->add('county_id', 'County-visible resources require a county.');
            }
        }];
    }
}
