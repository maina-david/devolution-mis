<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\County;
use App\Services\AnalyticsMetricCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnalyticsDashboardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(ProgrammePermission::ManageAnalytics->value) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('analytics_dashboards', 'code')],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:3000'],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'audience_roles' => ['required', 'array', 'min:1'],
            'audience_roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
            'widgets' => ['required', 'array', 'min:1', 'max:12'],
            'widgets.*.title' => ['required', 'string', 'max:160'],
            'widgets.*.description' => ['nullable', 'string', 'max:500'],
            'widgets.*.metric_key' => ['required', Rule::in(array_keys(app(AnalyticsMetricCatalogue::class)->options()))],
            'widgets.*.visualization' => ['required', Rule::in(['metric', 'bar', 'progress', 'table'])],
            'widgets.*.disaggregation' => ['nullable', Rule::in(['county'])],
            'widgets.*.filters' => ['nullable', 'array'],
            'widgets.*.filters.from' => ['nullable', 'date'],
            'widgets.*.filters.to' => ['nullable', 'date', 'after_or_equal:widgets.*.filters.from'],
            'widgets.*.position' => ['required', 'integer', 'min:1', 'max:12', 'distinct'],
            'widgets.*.width' => ['required', 'integer', 'min:1', 'max:3'],
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $countyId = $this->input('county_id');
            $county = is_string($countyId) ? County::query()->find($countyId) : null;
            if ($county instanceof County && $this->user()?->canAccessCounty($county) !== true) {
                $validator->errors()->add('county_id', 'The selected county is outside your authorized scope.');
            }
        }];
    }
}
