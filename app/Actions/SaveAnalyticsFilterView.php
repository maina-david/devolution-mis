<?php

namespace App\Actions;

use App\Models\AnalyticsFilterView;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SaveAnalyticsFilterView
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{name: string, filters: array<string, mixed>, is_default?: bool} $attributes */
    public function handle(User $actor, array $attributes): AnalyticsFilterView
    {
        return DB::transaction(function () use ($actor, $attributes): AnalyticsFilterView {
            if ((bool) ($attributes['is_default'] ?? false)) {
                AnalyticsFilterView::query()->where('user_id', $actor->id)->where('is_default', true)->lockForUpdate()->update(['is_default' => false]);
            }

            $view = AnalyticsFilterView::create([
                'user_id' => $actor->id,
                'name' => $attributes['name'],
                'filters' => array_filter($attributes['filters'], fn (mixed $value): bool => $value !== null && $value !== ''),
                'is_default' => (bool) ($attributes['is_default'] ?? false),
            ]);

            $this->auditLogger->record($actor, $view, 'analytics.filter_view.created', __('analytics.audit.filter_saved', ['name' => $view->name]), metadata: ['is_default' => $view->is_default, 'filter_keys' => array_keys($view->filters)]);

            return $view;
        });
    }
}
