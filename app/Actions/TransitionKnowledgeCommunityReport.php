<?php

namespace App\Actions;

use App\Models\KnowledgeCommunityReport;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionKnowledgeCommunityReport
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    public function handle(KnowledgeCommunityReport $report, User $actor, string $transition, string $rationale, ?string $resolution, ?string $postAction): KnowledgeCommunityReport
    {
        return DB::transaction(function () use ($report, $actor, $transition, $rationale, $resolution, $postAction): KnowledgeCommunityReport {
            $lockedReport = KnowledgeCommunityReport::query()->with(['workflowInstance', 'reporter', 'post'])->lockForUpdate()->findOrFail($report->id);
            abort_if($lockedReport->post->author_id === $actor->id, 403, 'Contribution authors cannot adjudicate reports about their own content.');
            $instance = $this->transitionWorkflow->handle($lockedReport->workflowInstance, $transition, $actor, ['resolution_present' => filled($resolution)], $rationale);
            $isDecision = in_array($transition, ['resolve', 'dismiss'], true);
            $lockedReport->update([
                'status' => $instance->current_state,
                'triaged_by' => $transition === 'triage' ? $actor->id : $lockedReport->triaged_by,
                'triaged_at' => $transition === 'triage' ? now() : $lockedReport->triaged_at,
                'decided_by' => $isDecision ? $actor->id : null,
                'decided_at' => $isDecision ? now() : null,
                'resolution' => $isDecision ? $resolution : null,
                'post_action' => $isDecision ? $postAction : null,
            ]);
            if ($isDecision && is_string($postAction)) {
                $lockedReport->post->forceFill(['is_moderated' => true, 'moderation_status' => $postAction === 'hide' ? 'hidden' : 'visible', 'moderated_by' => $actor->id, 'moderated_at' => now(), 'moderation_reason' => "{$lockedReport->reference}: {$resolution}"])->save();
            }
            $this->auditLogger->record($actor, $lockedReport, 'knowledge.community_report.transitioned', "Community report {$lockedReport->reference} transitioned to {$instance->current_state}.", $lockedReport->county_id, ['transition' => $transition, 'rationale' => $rationale, 'post_action' => $postAction]);
            $lockedReport->reporter->notify(new ProgrammeAlert('Community report updated', "{$lockedReport->reference} is now {$instance->current_state}.", 'knowledge', route('knowledge.index')));

            return $lockedReport->refresh();
        });
    }
}
