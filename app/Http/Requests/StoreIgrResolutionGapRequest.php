<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIgrResolutionGapRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::UpdateIgrResolutions->value) === true || $this->user()?->can(ProgrammePermission::ManageIgrResolutions->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'igr_gap_category_id' => ['required', 'uuid', Rule::exists('igr_gap_categories', 'id')->where(fn (Builder $query) => $query->where('is_active', true)->whereNull('deleted_at'))],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'owner_user_id' => ['required', 'uuid', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'impact' => ['required', 'string', 'min:20', 'max:10000'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'due_on' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
