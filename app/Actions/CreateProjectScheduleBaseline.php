<?php

namespace App\Actions;

use App\Models\DevolutionProject;
use App\Models\ProjectScheduleBaseline;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProjectScheduleAnalyzer;
use Illuminate\Support\Facades\DB;

class CreateProjectScheduleBaseline
{
    public function __construct(private ProjectScheduleAnalyzer $analyzer, private AuditLogger $auditLogger) {}

    public function handle(DevolutionProject $project, User $actor, string $reason): ProjectScheduleBaseline
    {
        abort_if($project->status === 'closed' || $project->lifecycle_stage === 'closed', 409, 'Closed projects cannot create schedule baselines.');

        return DB::transaction(function () use ($project, $actor, $reason): ProjectScheduleBaseline {
            $project = DevolutionProject::query()->lockForUpdate()->findOrFail($project->id);
            abort_if($project->scheduleBaselines()->where('status', 'pending')->exists(), 409, 'This project already has a schedule baseline awaiting independent review.');
            $milestones = $project->milestones()->orderBy('code')->get();
            abort_unless(abs((float) $milestones->sum('weight') - 100.0) < 0.001, 422, 'Milestone weights must total exactly 100% before a schedule baseline is captured.');
            $analysis = $this->analyzer->analyze($milestones);
            $snapshot = $this->analyzer->snapshot($milestones);
            $checksum = $this->analyzer->checksum($snapshot, $analysis);
            $baseline = $project->scheduleBaselines()->create([
                'version' => ((int) $project->scheduleBaselines()->max('version')) + 1,
                'schedule_snapshot' => $snapshot,
                'critical_path_analysis' => $analysis,
                'snapshot_checksum' => $checksum,
                'baseline_reason' => $reason,
                'requested_by' => $actor->id,
            ]);
            $this->auditLogger->record($actor, $baseline, 'project.schedule_baseline_requested', "Project schedule baseline version {$baseline->version} submitted for independent approval.", $project->lead_county_id, ['project_id' => $project->id, 'snapshot_checksum' => $checksum, 'critical_path_codes' => $analysis['critical_path_codes']]);

            return $baseline;
        });
    }
}
