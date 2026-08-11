<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\AnalyticsDashboard;
use App\Models\County;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReportScheduleRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('report_schedules', 'code')],
            'name' => ['required', 'string', 'max:200'],
            'workspace' => ['required', Rule::in(['analytics-dashboard'])],
            'county_id' => ['nullable', 'uuid', 'exists:counties,id'],
            'format' => ['required', Rule::in(['csv', 'json', 'xlsx', 'pdf'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'filters' => ['required', 'array'],
            'filters.dashboard_id' => ['required', 'uuid', 'exists:analytics_dashboards,id'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date', 'after_or_equal:filters.from'],
            'recipient_user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'recipient_user_ids.*' => ['required', 'uuid', 'distinct', 'exists:users,id'],
            'next_run_at' => ['required', 'date', 'after:now'],
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

            $dashboardId = $this->input('filters.dashboard_id');
            $dashboard = is_string($dashboardId) ? AnalyticsDashboard::query()->find($dashboardId) : null;
            if ($dashboard instanceof AnalyticsDashboard && $dashboard->status !== 'published') {
                $validator->errors()->add('filters.dashboard_id', 'Only independently published dashboards can be scheduled.');
            }
            if ($dashboard instanceof AnalyticsDashboard && $dashboard->county_id !== null && $dashboard->county_id !== $countyId) {
                $validator->errors()->add('county_id', 'The schedule county must match the dashboard county.');
            }
        }];
    }
}
