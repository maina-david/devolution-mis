<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProcessingActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $data_asset_id
 * @property string|null $retention_schedule_id
 * @property string|null $submitted_by
 * @property string|null $reviewed_by
 * @property string $reference
 * @property string $name
 * @property string $purpose
 * @property string $lawful_basis
 * @property string $lawful_basis_reference
 * @property string $controller_name
 * @property list<string>|null $processor_names
 * @property list<string>|null $recipient_categories
 * @property list<string> $processing_operations
 * @property bool $automated_decision_making
 * @property bool $cross_border_transfer
 * @property list<string>|null $transfer_countries
 * @property string|null $transfer_safeguards
 * @property string $dpia_status
 * @property string|null $dpia_reference
 * @property string|null $risk_summary
 * @property string $security_measures
 * @property string $status
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $next_review_at
 * @property DataAsset $dataAsset
 * @property RetentionSchedule|null $retentionSchedule
 * @property User|null $submitter
 * @property User|null $reviewer
 */
#[Fillable(['data_asset_id', 'retention_schedule_id', 'submitted_by', 'reviewed_by', 'reference', 'name', 'purpose', 'lawful_basis', 'lawful_basis_reference', 'controller_name', 'processor_names', 'recipient_categories', 'processing_operations', 'automated_decision_making', 'cross_border_transfer', 'transfer_countries', 'transfer_safeguards', 'dpia_status', 'dpia_reference', 'risk_summary', 'security_measures', 'status', 'submitted_at', 'reviewed_at', 'next_review_at'])]
class ProcessingActivity extends Model
{
    /** @use HasFactory<ProcessingActivityFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['automated_decision_making' => false, 'cross_border_transfer' => false, 'dpia_status' => 'screening_required', 'status' => 'draft'];

    protected function casts(): array
    {
        return ['processor_names' => 'array', 'recipient_categories' => 'array', 'processing_operations' => 'array', 'automated_decision_making' => 'boolean', 'cross_border_transfer' => 'boolean', 'transfer_countries' => 'array', 'submitted_at' => 'immutable_datetime', 'reviewed_at' => 'immutable_datetime', 'next_review_at' => 'date'];
    }

    /** @return BelongsTo<DataAsset, $this> */
    public function dataAsset(): BelongsTo
    {
        return $this->belongsTo(DataAsset::class);
    }

    /** @return BelongsTo<RetentionSchedule, $this> */
    public function retentionSchedule(): BelongsTo
    {
        return $this->belongsTo(RetentionSchedule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
