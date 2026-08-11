<?php

namespace App\Models;

use Database\Factories\AnalyticsWidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $analytics_dashboard_id
 * @property string $title
 * @property string|null $description
 * @property string $metric_key
 * @property string $visualization
 * @property string|null $disaggregation
 * @property array<string, mixed>|null $filters
 * @property int $position
 * @property int $width
 * @property-read AnalyticsDashboard $dashboard
 */
#[Fillable(['analytics_dashboard_id', 'title', 'description', 'metric_key', 'visualization', 'disaggregation', 'filters', 'position', 'width'])]
class AnalyticsWidget extends Model
{
    /** @use HasFactory<AnalyticsWidgetFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['filters' => 'array', 'position' => 'integer', 'width' => 'integer'];
    }

    /** @return BelongsTo<AnalyticsDashboard, $this> */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(AnalyticsDashboard::class, 'analytics_dashboard_id');
    }
}
