<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManagePerformanceCycles->value) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:performance_cycles,code'], 'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after:period_start'],
            'goal_setting_deadline' => ['required', 'date', 'between:period_start,period_end'], 'midterm_review_deadline' => ['nullable', 'date', 'between:period_start,period_end'],
            'final_review_deadline' => ['required', 'date', 'between:period_start,period_end'], 'status' => ['required', 'in:draft,open,closed'],
        ];
    }
}
