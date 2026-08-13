<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsWidget;
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
            'filters' => ['required', 'array:search,status,county_id,from,to,per_page,dashboard_id,widget_id,visualization,time_grain'],
            'filters.search' => ['nullable', 'string', 'max:100'],
            'filters.status' => ['nullable', Rule::in(['draft', 'published'])],
            'filters.county_id' => ['nullable', 'uuid'],
            'filters.from' => ['nullable', 'date_format:Y-m-d'],
            'filters.to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:filters.from'],
            'filters.per_page' => ['nullable', 'integer', 'in:10,15,25,50'],
            'filters.dashboard_id' => ['nullable', 'uuid', 'exists:analytics_dashboards,id'],
            'filters.widget_id' => ['nullable', 'uuid', 'exists:analytics_widgets,id'],
            'filters.visualization' => ['nullable', Rule::in(['metric', 'bar', 'line', 'area', 'progress', 'table'])],
            'filters.time_grain' => ['nullable', Rule::in(['month', 'quarter', 'year'])],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $countyId = $this->input('filters.county_id');
            if (is_string($countyId) && ! $validator->errors()->has('filters.county_id')) {
                $county = County::query()->find($countyId);
                if (! $county instanceof County || $this->user()?->canAccessCounty($county) !== true) {
                    $validator->errors()->add('filters.county_id', 'The selected county is outside your authorized scope.');
                }
            }

            $dashboardId = $this->input('filters.dashboard_id');
            $widgetId = $this->input('filters.widget_id');
            if (is_string($dashboardId) && ! $validator->errors()->has('filters.dashboard_id')) {
                $dashboard = AnalyticsDashboard::query()->with('county')->find($dashboardId);
                $hasDashboardAccess = $dashboard instanceof AnalyticsDashboard
                    && ($dashboard->county === null || $this->user()?->canAccessCounty($dashboard->county) === true)
                    && ($dashboard->status === 'published'
                        || $this->user()?->can(ProgrammePermission::ManageAnalytics->value) === true
                        || $this->user()?->can(ProgrammePermission::ApproveAnalytics->value) === true);
                if (! $hasDashboardAccess) {
                    $validator->errors()->add('filters.dashboard_id', 'The selected dashboard is outside your authorized scope.');
                }
            }
            if (is_string($widgetId) && is_string($dashboardId) && ! $validator->errors()->has('filters.widget_id')) {
                $belongsToDashboard = AnalyticsWidget::query()
                    ->whereKey($widgetId)
                    ->where('analytics_dashboard_id', $dashboardId)
                    ->exists();
                if (! $belongsToDashboard) {
                    $validator->errors()->add('filters.widget_id', 'The selected widget does not belong to the selected dashboard.');
                }
            }
        }];
    }
}
