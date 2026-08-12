<?php

namespace App\Actions;

use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgePost;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateKnowledgeCommunityReport
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger) {}

    /** @param array{category: string, severity: string, description: string} $attributes */
    public function handle(KnowledgePost $post, User $reporter, array $attributes): KnowledgeCommunityReport
    {
        return DB::transaction(function () use ($post, $reporter, $attributes): KnowledgeCommunityReport {
            $lockedPost = KnowledgePost::query()->with('discussion')->lockForUpdate()->findOrFail($post->id);
            abort_if($lockedPost->author_id === $reporter->id, 403, 'Authors cannot report their own contribution through the abuse workflow.');
            abort_unless($lockedPost->moderation_status === 'visible' && $lockedPost->discussion->status === 'open', 409, 'Only visible contributions in open discussions can be reported.');

            $duplicateExists = KnowledgeCommunityReport::query()->where('knowledge_post_id', $lockedPost->id)->where('reported_by', $reporter->id)->whereIn('status', ['reported', 'investigating'])->exists();
            if ($duplicateExists) {
                throw ValidationException::withMessages(['description' => 'You already have an active report for this contribution.']);
            }

            $report = KnowledgeCommunityReport::create([
                ...$attributes,
                'knowledge_post_id' => $lockedPost->id,
                'county_id' => $lockedPost->discussion->county_id,
                'reported_by' => $reporter->id,
                'reference' => 'KMR-'.now()->format('Y').'-'.str_pad((string) (KnowledgeCommunityReport::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
                'status' => 'reported',
            ]);
            $definition = WorkflowDefinition::query()->where('code', 'KNOWLEDGE-COMMUNITY-MODERATION')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $report, $reporter, ['resolution_present' => false, 'severity' => $report->severity], $report->county_id);
            $report->update(['workflow_instance_id' => $instance->id]);
            $this->auditLogger->record($reporter, $report, 'knowledge.community_report.created', "Community report {$report->reference} submitted.", $report->county_id, ['category' => $report->category, 'severity' => $report->severity]);
            $reporter->notify(new ProgrammeAlert('Community report received', "{$report->reference} has entered the governed moderation queue.", 'knowledge', route('knowledge.index')));

            return $report->refresh();
        });
    }
}
