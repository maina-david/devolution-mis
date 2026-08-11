<?php

namespace App\Actions;

use App\Models\AnalyticsDashboard;
use App\Models\County;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateReportSchedule
{
    public function __construct(
        private AuditLogger $auditLogger,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): ReportSchedule
    {
        return DB::transaction(function () use ($actor, $attributes): ReportSchedule {
            $dashboardId = $attributes['filters']['dashboard_id'] ?? null;
            $dashboard = is_string($dashboardId) ? AnalyticsDashboard::query()->find($dashboardId) : null;
            if (! $dashboard instanceof AnalyticsDashboard || $dashboard->status !== 'published') {
                throw ValidationException::withMessages(['filters.dashboard_id' => 'A published governed dashboard is required.']);
            }
            if ($dashboard->reference_data_release_id === null) {
                throw ValidationException::withMessages(['filters.dashboard_id' => 'A published dashboard with verified reference-data lineage is required.']);
            }
            $county = is_string($attributes['county_id'] ?? null) ? County::query()->find($attributes['county_id']) : null;
            if ($county instanceof County && ! $actor->canAccessCounty($county)) {
                throw ValidationException::withMessages(['county_id' => 'The selected county is outside your authorized scope.']);
            }
            $recipients = User::query()->whereKey($attributes['recipient_user_ids'])->get();
            if ($recipients->count() !== count($attributes['recipient_user_ids'])) {
                throw ValidationException::withMessages(['recipient_user_ids' => 'Every report recipient must be an active programme identity.']);
            }
            $invalidRecipient = $recipients->first(function (User $recipient) use ($dashboard, $county): bool {
                $audience = in_array($recipient->programmeRole()->value, $dashboard->audience_roles, true);
                $scope = $county instanceof County
                    ? $recipient->canAccessCounty($county)
                    : $recipient->programmeRole()->hasNationalScope();

                return ! $audience || ! $scope;
            });
            if ($invalidRecipient instanceof User) {
                throw ValidationException::withMessages(['recipient_user_ids' => "{$invalidRecipient->name} is outside the dashboard audience or report county scope."]);
            }

            $referenceDataRelease = $this->referenceDataReleaseResolver->forReportSchedule($county?->id, now());
            $schedule = ReportSchedule::create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id, 'created_by' => $actor->id, 'status' => 'draft']);
            $this->auditLogger->record($actor, $schedule, 'analytics.report-schedule.created', "Scheduled report {$schedule->code} created as a draft.", $schedule->county_id, ['reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $schedule;
        });
    }
}
