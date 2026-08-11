<?php

namespace App\Http\Requests;

use App\Enums\ProgrammePermission;
use App\Models\AnalyticsDashboard;
use App\Services\AnalyticsMetricCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalyticsWidgetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $dashboard = $this->route('dashboard');

        return $this->user()?->can(ProgrammePermission::ManageAnalytics->value) === true
            && $dashboard instanceof AnalyticsDashboard
            && $dashboard->status === 'draft';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $dashboard = $this->route('dashboard');

        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'metric_key' => ['required', Rule::in(array_keys(app(AnalyticsMetricCatalogue::class)->options()))],
            'visualization' => ['required', Rule::in(['metric', 'bar', 'progress', 'table'])],
            'disaggregation' => ['nullable', Rule::in(['county'])],
            'filters' => ['nullable', 'array'],
            'filters.from' => ['nullable', 'date'],
            'filters.to' => ['nullable', 'date', 'after_or_equal:filters.from'],
            'position' => ['required', 'integer', 'min:1', 'max:12', Rule::unique('analytics_widgets', 'position')->where('analytics_dashboard_id', $dashboard instanceof AnalyticsDashboard ? $dashboard->id : '')],
            'width' => ['required', 'integer', 'min:1', 'max:3'],
        ];
    }
}
