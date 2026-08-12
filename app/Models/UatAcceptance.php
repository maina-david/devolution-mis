<?php

namespace App\Models;

use Database\Factories\UatAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['uat_campaign_id', 'submitted_by', 'decided_by', 'decision', 'criteria_snapshot', 'coverage_snapshot', 'open_findings_count', 'checksum', 'decision_reason', 'submitted_at', 'decided_at'])]
class UatAcceptance extends Model
{
    /** @use HasFactory<UatAcceptanceFactory> */
    use HasFactory, HasUuids;

    protected $attributes = ['decision' => 'pending'];

    protected function casts(): array
    {
        return ['criteria_snapshot' => 'array', 'coverage_snapshot' => 'array', 'open_findings_count' => 'integer', 'submitted_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(UatCampaign::class, 'uat_campaign_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
