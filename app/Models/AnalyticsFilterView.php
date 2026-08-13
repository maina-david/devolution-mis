<?php

namespace App\Models;

use Database\Factories\AnalyticsFilterViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property array<string, mixed> $filters */
#[Fillable(['user_id', 'name', 'filters', 'is_default'])]
class AnalyticsFilterView extends Model
{
    /** @use HasFactory<AnalyticsFilterViewFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['is_default' => false];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['filters' => 'array', 'is_default' => 'boolean'];
    }
}
