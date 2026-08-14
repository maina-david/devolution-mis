<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->canAny([ProgrammePermission::CurateKnowledge->value, ProgrammePermission::ManageKnowledge->value]), 403, __('knowledge.errors.community_report_transition_unauthorized'));

        return DB::transaction(function () use ($report, $actor, $transition, $rationale, $resolution, $postAction): KnowledgeCommunityReport {
            $lockedReport = KnowledgeCommunityReport::query()->with(['workflowInstance', 'reporter', 'post', 'county'])->lockForUpdate()->findOrFail($report->id);
            abort_unless($lockedReport->county_id === null || ($lockedReport->county !== null && $actor->canAccessCounty($lockedReport->county)), 403, __('knowledge.errors.community_county_outside_scope'));
            abort_if($lockedReport->post->author_id === $actor->id, 403, __('knowledge.errors.community_report_author_adjudication'));
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
            $this->auditLogger->record($actor, $lockedReport, 'knowledge.community_report.transitioned', __('knowledge.audit.community_report_transitioned', ['reference' => $lockedReport->reference, 'state' => $instance->current_state]), $lockedReport->county_id, ['transition' => $transition, 'rationale' => $rationale, 'post_action' => $postAction]);
            $lockedReport->reporter->notify(ProgrammeAlert::translated('knowledge.notifications.community_report_updated_title', 'knowledge.notifications.community_report_updated_message', 'knowledge', messageParameters: ['reference' => $lockedReport->reference, 'state' => $instance->current_state], url: route('knowledge.index')));

            return $lockedReport->refresh();
        });
    }
}
