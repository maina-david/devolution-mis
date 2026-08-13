<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\County;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnalyticsFilterViewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ViewAnalytics->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('analytics_filter_views', 'name')->where('user_id', $this->user()?->id)->whereNull('deleted_at')],
            'is_default' => ['sometimes', 'boolean'],
            'filters' => ['required', 'array:search,status,county_id,from,to,per_page'],
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.status' => ['nullable', Rule::in(['draft', 'published'])],
            'filters.county_id' => ['nullable', 'uuid'],
            'filters.from' => ['nullable', 'date_format:Y-m-d'],
            'filters.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filters.from'],
            'filters.per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $countyId = $this->input('filters.county_id');
            if (! is_string($countyId) || $validator->errors()->has('filters.county_id')) {
                return;
            }

            $county = County::query()->find($countyId);
            if (! $county instanceof County || $this->user()?->canAccessCounty($county) !== true) {
                $validator->errors()->add('filters.county_id', 'The selected county is outside your authorized scope.');
            }
        }];
    }
}
