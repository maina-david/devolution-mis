<?php

namespace App\Actions;

use App\Models\DevolutionProject;
use App\Models\ProjectScheduleBaseline;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProjectScheduleAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideProjectScheduleBaseline
{
    public function __construct(private ProjectScheduleAnalyzer $analyzer, private AuditLogger $auditLogger) {}

    public function handle(ProjectScheduleBaseline $baseline, User $actor, string $decision, string $rationale): ProjectScheduleBaseline
    {
        return DB::transaction(function () use ($baseline, $actor, $decision, $rationale): ProjectScheduleBaseline {
            $baseline = ProjectScheduleBaseline::query()->with('project')->lockForUpdate()->findOrFail($baseline->id);
            abort_unless($baseline->status === 'pending', 409, 'Only pending schedule baselines can be decided.');
            if ($baseline->requested_by === $actor->id) {
                throw ValidationException::withMessages(['decision' => 'The baseline requester cannot approve or reject their own schedule baseline.']);
            }
            if ($decision === 'approve') {
                $milestones = $baseline->project->milestones()->orderBy('code')->get();
                $analysis = $this->analyzer->analyze($milestones);
                $snapshot = $this->analyzer->snapshot($milestones);
                if (! hash_equals($baseline->snapshot_checksum, $this->analyzer->checksum($snapshot, $analysis))) {
                    throw ValidationException::withMessages(['decision' => 'The live milestone schedule changed after capture. Reject this request and capture a new baseline.']);
                }
            }

            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $decisionEvidence = ['baseline_id' => $baseline->id, 'snapshot_checksum' => $baseline->snapshot_checksum, 'status' => $status, 'decided_by' => $actor->id, 'rationale' => $rationale, 'decided_at' => now()->toIso8601String()];
            $baseline->update(['status' => $status, 'decided_by' => $actor->id, 'decision_rationale' => $rationale, 'decision_checksum' => hash('sha256', json_encode($decisionEvidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), 'decided_at' => now()]);
            /** @var DevolutionProject $project */
            $project = $baseline->project;
            $this->auditLogger->record($actor, $baseline, "project.schedule_baseline_{$status}", "Project schedule baseline version {$baseline->version} {$status} after independent review.", $project->lead_county_id, ['project_id' => $project->id, 'snapshot_checksum' => $baseline->snapshot_checksum, 'decision_checksum' => $baseline->decision_checksum]);

            return $baseline->refresh();
        });
    }
}
