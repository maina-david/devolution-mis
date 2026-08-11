<?php

namespace App\Actions;

use App\Models\ProjectProgressUpdate;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyProjectProgress
{
    public function __construct(private AuditLogger $auditLogger, private IngestVerifiedProjectResults $ingestResults) {}

    public function handle(ProjectProgressUpdate $update, User $actor, string $status, string $rationale): ProjectProgressUpdate
    {
        $project = $update->project;
        abort_unless($project->counties()->get()->contains(fn ($county): bool => $actor->canAccessCounty($county)), 403);
        if ($update->submitted_by === $actor->id) {
            throw ValidationException::withMessages(['status' => 'The submitter cannot verify their own project update.']);
        }
        if ($update->verification_status === 'verified') {
            throw ValidationException::withMessages(['status' => 'A verified progress update is immutable.']);
        }

        DB::transaction(function () use ($update, $project, $actor, $status, $rationale): void {
            $update->update(['verification_status' => $status, 'verification_rationale' => $rationale, 'verified_by' => $actor->id, 'verified_at' => now()]);
            if ($status === 'verified' && $update->reporting_date->gte($project->progressUpdates()->where('verification_status', 'verified')->whereKeyNot($update->id)->max('reporting_date') ?? '1900-01-01')) {
                $project->update(['physical_progress' => $update->physical_progress]);
            }
            if ($status === 'verified') {
                $this->ingestResults->handle($update->refresh(), $actor);
            }
        });
        $this->auditLogger->record($actor, $update, 'project.progress_verified', "Project progress marked {$status}.", $project->lead_county_id, ['rationale' => $rationale]);

        return $update->refresh();
    }
}
