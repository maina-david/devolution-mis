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
                throw ValidationException::withMessages(['filters.dashboard_id' => __('analytics.errors.published_dashboard_required')]);
            }
            if ($dashboard->reference_data_release_id === null) {
                throw ValidationException::withMessages(['filters.dashboard_id' => __('analytics.errors.dashboard_lineage_required')]);
            }
            $county = is_string($attributes['county_id'] ?? null) ? County::query()->find($attributes['county_id']) : null;
            if ($county instanceof County && ! $actor->canAccessCounty($county)) {
                throw ValidationException::withMessages(['county_id' => __('analytics.errors.county_outside_scope')]);
            }
            $recipients = User::query()->whereKey($attributes['recipient_user_ids'])->get();
            if ($recipients->count() !== count($attributes['recipient_user_ids'])) {
                throw ValidationException::withMessages(['recipient_user_ids' => __('analytics.errors.active_recipient_required')]);
            }
            $invalidRecipient = $recipients->first(function (User $recipient) use ($dashboard, $county): bool {
                $audience = in_array($recipient->programmeRole()->value, $dashboard->audience_roles, true);
                $scope = $county instanceof County
                    ? $recipient->canAccessCounty($county)
                    : $recipient->programmeRole()->hasNationalScope();

                return ! $audience || ! $scope;
            });
            if ($invalidRecipient instanceof User) {
                throw ValidationException::withMessages(['recipient_user_ids' => __('analytics.errors.recipient_outside_scope', ['recipient' => $invalidRecipient->name])]);
            }

            $referenceDataRelease = $this->referenceDataReleaseResolver->forReportSchedule($county?->id, now());
            $schedule = ReportSchedule::create([...$attributes, 'reference_data_release_id' => $referenceDataRelease->id, 'created_by' => $actor->id, 'status' => 'draft']);
            $this->auditLogger->record($actor, $schedule, 'analytics.report-schedule.created', __('analytics.audit.schedule_created', ['code' => $schedule->code]), $schedule->county_id, ['reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $schedule;
        });
    }
}
