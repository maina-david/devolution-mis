<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IgrResolutionUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $progress_percentage
 * @property string $narrative
 * @property string|null $implementation_gap
 * @property string|null $evidence_reference
 * @property CarbonImmutable $reported_at
 */
#[Fillable(['igr_resolution_id', 'progress_percentage', 'narrative', 'implementation_gap', 'evidence_reference', 'reported_by', 'reported_at'])]
class IgrResolutionUpdate extends Model
{
    /** @use HasFactory<IgrResolutionUpdateFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected function casts(): array
    {
        return ['progress_percentage' => 'integer', 'reported_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<IgrResolution, $this> */
    public function resolution(): BelongsTo
    {
        return $this->belongsTo(IgrResolution::class, 'igr_resolution_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
