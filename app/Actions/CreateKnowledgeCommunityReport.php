<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($reporter->can(ProgrammePermission::ContributeKnowledge->value), 403, __('knowledge.errors.community_report_create_unauthorized'));

        return DB::transaction(function () use ($post, $reporter, $attributes): KnowledgeCommunityReport {
            $lockedPost = KnowledgePost::query()->with('discussion.county')->lockForUpdate()->findOrFail($post->id);
            abort_unless($lockedPost->discussion->county_id === null || ($lockedPost->discussion->county !== null && $reporter->canAccessCounty($lockedPost->discussion->county)), 403, __('knowledge.errors.community_county_outside_scope'));
            abort_if($lockedPost->author_id === $reporter->id, 403, __('knowledge.errors.community_report_own_post'));
            abort_unless($lockedPost->moderation_status === 'visible' && $lockedPost->discussion->status === 'open', 409, __('knowledge.errors.community_report_visible_open_only'));

            $duplicateExists = KnowledgeCommunityReport::query()->where('knowledge_post_id', $lockedPost->id)->where('reported_by', $reporter->id)->whereIn('status', ['reported', 'investigating'])->exists();
            if ($duplicateExists) {
                throw ValidationException::withMessages(['description' => __('knowledge.errors.community_report_duplicate')]);
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
            $this->auditLogger->record($reporter, $report, 'knowledge.community_report.created', __('knowledge.audit.community_report_created', ['reference' => $report->reference]), $report->county_id, ['category' => $report->category, 'severity' => $report->severity]);
            $reporter->notify(ProgrammeAlert::translated('knowledge.notifications.community_report_received_title', 'knowledge.notifications.community_report_received_message', 'knowledge', messageParameters: ['reference' => $report->reference], url: route('knowledge.index')));

            return $report->refresh();
        });
    }
}
