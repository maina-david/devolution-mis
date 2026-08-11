<?php

namespace App\Models;

use Database\Factories\KnowledgeCommunityReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $triaged_at
 * @property Carbon|null $decided_at
 */
#[Fillable(['workflow_instance_id', 'knowledge_post_id', 'county_id', 'reported_by', 'triaged_by', 'decided_by', 'reference', 'category', 'severity', 'description', 'status', 'resolution', 'post_action', 'triaged_at', 'decided_at'])]
class KnowledgeCommunityReport extends Model
{
    /** @use HasFactory<KnowledgeCommunityReportFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $attributes = ['status' => 'reported'];

    protected function casts(): array
    {
        return ['triaged_at' => 'immutable_datetime', 'decided_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    /** @return BelongsTo<KnowledgePost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(KnowledgePost::class, 'knowledge_post_id');
    }

    /** @return BelongsTo<County, $this> */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function triager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
