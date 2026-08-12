<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkspaceIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'cycle_id' => ['nullable', 'uuid', 'exists:assessment_cycles,id'],
            'status' => ['nullable', 'string', 'max:50'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'],
            'classroom_id' => ['nullable', 'uuid', 'exists:virtual_classrooms,id'],
            'item_type' => ['nullable', 'in:best_practice,case_study,research,publication,toolkit,blog'],
            'tag' => ['nullable', 'string', 'max:100'],
            'report_status' => ['nullable', 'in:reported,investigating,resolved,dismissed'],
            'report_search' => ['nullable', 'string', 'max:100'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'uat_from' => ['nullable', 'date'],
            'uat_to' => ['nullable', 'date', 'after_or_equal:uat_from'],
            'uat_search' => ['nullable', 'string', 'max:100'],
            'uat_status' => ['nullable', 'in:planning,executing,review,accepted,rejected'],
            'uat_county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'uat_per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
            'uat_page' => ['nullable', 'integer', 'min:1'],
            'gap_category_id' => ['nullable', 'uuid', 'exists:igr_gap_categories,id'],
            'ids' => ['nullable', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return list<string> */
    public function selectedIds(): array
    {
        $ids = $this->validated('ids', []);

        return is_array($ids)
            ? array_values(array_filter($ids, is_string(...)))
            : [];
    }
}
