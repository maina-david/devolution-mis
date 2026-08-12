<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\RolloutWave;
use App\Models\TrainingCohort;
use App\Models\UatCampaign;
use App\Models\User;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class ChangeReadinessSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal() || RolloutWave::query()->exists()) {
            return;
        }
        $creator = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $facilitator = User::query()->where('email', 'platform.admin@idmis.test')->first();
        $counties = County::query()->orderBy('code')->get();
        if (! $creator || ! $facilitator || $counties->count() !== 47) {
            return;
        }

        $wave = RolloutWave::create(['created_by' => $creator->id, 'code' => 'NATIONAL-ROLL-OUT-PLAN', 'name' => 'Proposed 47-county national rollout', 'objective' => 'Plan the ToR training baseline and phased support coverage while retaining delivery evidence as pending until attendance, competency, UAT and owner acceptance are recorded.', 'starts_on' => now()->addMonths(3)->startOfMonth(), 'ends_on' => now()->addMonths(8)->endOfMonth(), 'planned_participants' => 336, 'status' => 'planning', 'entry_criteria' => ['County sponsor and champion nominated', 'Connectivity and device assessment completed', 'Data migration rehearsal reconciled', 'Role assignments independently reviewed', 'Pilot findings closed for the wave'], 'support_channels' => ['National help desk', 'County champion escalation', 'Knowledge base', 'Incident bridge'], 'help_desk_rehearsed' => false, 'training_materials_approved' => false, 'readiness_notes' => 'Planning baseline only. Zero attendance, competency, UAT or rollout acceptance is implied.']);
        $wave->counties()->sync($counties->mapWithKeys(fn (County $county): array => [$county->id => ['readiness_status' => 'planned', 'readiness_note' => 'County readiness assessment pending.']])->all());

        $tracks = [
            [UserRole::CountyOfficial, 'County reporting and evidence operators'],
            [UserRole::CountyAdmin, 'County administrators and access coordinators'],
            [UserRole::Assessor, 'Independent assessment and verification agents'],
            [UserRole::DevelopmentPartner, 'Development partner portfolio operators'],
            [UserRole::TopManagement, 'Executive dashboards and decision oversight'],
            [UserRole::DevolutionAdmin, 'National programme and workflow administrators'],
            [UserRole::PlatformAdmin, 'Platform, security and service administrators'],
        ];
        foreach ($tracks as $index => [$role, $name]) {
            TrainingCohort::create(['rollout_wave_id' => $wave->id, 'facilitator_id' => $facilitator->id, 'code' => sprintf('NAT-%02d-%s', $index + 1, strtoupper(str_replace('-', '', $role->value))), 'name' => $name, 'audience_role' => $role->value, 'delivery_mode' => 'blended', 'language' => ReferenceCatalogue::defaultLanguage(), 'venue' => 'Regional hub and approved virtual classroom — confirmation pending', 'seat_capacity' => 48, 'minimum_attendance_hours' => 12, 'passing_score' => 70, 'starts_at' => now()->addMonths(3)->addWeeks($index), 'ends_at' => now()->addMonths(3)->addWeeks($index)->addDays(2), 'status' => 'planned']);
        }

        $this->seedPilotUatPlan($creator, $counties);
    }

    /** @param Collection<int, County> $counties */
    private function seedPilotUatPlan(User $creator, $counties): void
    {
        if (UatCampaign::query()->exists()) {
            return;
        }

        $pilotCountyCodes = ['001', '007', '012', '022', '030', '047'];
        $pilotCounties = $counties->whereIn('code', $pilotCountyCodes)->values();
        if ($pilotCounties->count() !== count($pilotCountyCodes)) {
            return;
        }

        $release = app(EffectiveReferenceDataReleaseResolver::class)->forUatCampaign($pilotCounties->pluck('id')->all(), now());
        $campaign = UatCampaign::create([
            'reference_data_release_id' => $release->id,
            'created_by' => $creator->id,
            'code' => 'IDMIS-PILOT-UAT-2026',
            'name' => 'Representative six-county IDMIS pilot acceptance',
            'objective' => 'Plan representative end-to-end verification of the fourteen ToR modules and shared platform controls before any phased production rollout decision.',
            'environment' => 'government-hosting-uat',
            'starts_on' => now()->addMonths(2)->startOfMonth(),
            'ends_on' => now()->addMonths(3)->endOfMonth(),
            'status' => 'planning',
            'acceptance_criteria' => [
                'Every required scenario has a passing latest execution in every participating pilot county.',
                'Every critical, high, medium and low finding is independently verified as closed.',
                'Representative national, county, assessor and partner roles complete their assigned journeys.',
                'Keyboard, screen-reader and constrained-connectivity variants are evidenced.',
                'The independent acceptance authority records a checksummed decision after reviewing the full evidence set.',
            ],
            'required_roles' => collect(UserRole::cases())->map->value->all(),
            'minimum_counties' => $pilotCounties->count(),
        ]);
        $campaign->counties()->sync($pilotCounties->mapWithKeys(fn (County $county): array => [$county->id => ['participation_status' => 'planned', 'participation_note' => 'Representative pilot participation is planned; execution evidence has not yet been recorded.']])->all());

        $scenarios = [
            ['CFM', 'citizen-feedback', 'Submit and track a public citizen case', UserRole::CountyOfficial],
            ['ELP', 'e-learning', 'Complete an offline-capable learning and competency journey', UserRole::CountyOfficial],
            ['PCO', 'partner-coordination', 'Submit and reconcile a partner contribution', UserRole::DevelopmentPartner],
            ['DSWG', 'dswg-coordination', 'Govern a DSWG meeting decision and action', UserRole::DevelopmentPartner],
            ['PRJ', 'project-management', 'Baseline and verify a county project delivery update', UserRole::CountyAdmin],
            ['DPM', 'departmental-performance', 'Submit and independently review a departmental performance plan', UserRole::DevolutionAdmin],
            ['ME', 'monitoring-evaluation', 'Submit, verify and aggregate an indicator observation', UserRole::CountyOfficial],
            ['GRM', 'grievance-redress', 'Triage, resolve and independently close a grievance', UserRole::CountyAdmin],
            ['DMS', 'central-repository', 'Upload, scan, preview, classify and retrieve governed evidence', UserRole::CountyOfficial],
            ['ANL', 'analytics-reporting', 'Publish a governed dashboard and scheduled report', UserRole::TopManagement],
            ['IGR', 'igr-resolutions', 'Track and independently close an intergovernmental resolution', UserRole::CountyAdmin],
            ['ACPA', 'devolution-assessment', 'Submit and independently verify an ACPA evidence package', UserRole::Assessor],
            ['TRV', 'travel-clearance', 'Complete a separated travel-clearance approval chain', UserRole::CountyOfficial],
            ['KM', 'knowledge-management', 'Publish and independently curate a reusable knowledge asset', UserRole::CountyOfficial],
            ['PLT', 'shared-platform', 'Verify RBAC, county isolation, audit, notification and export controls', UserRole::PlatformAdmin],
        ];

        foreach ($scenarios as [$code, $module, $title, $role]) {
            $campaign->scenarios()->create([
                'created_by' => $creator->id,
                'code' => "UAT-{$code}-001",
                'module' => $module,
                'title' => $title,
                'actor_role' => $role->value,
                'priority' => 'critical',
                'journey' => "A representative {$role->label()} completes the governed {$title} journey using production-like pilot data.",
                'preconditions' => ['The actor has an active administrator-granted account and correct county or portfolio scope.', 'Required governed catalogue, workflow and document controls are published.', 'No production acceptance is inferred from this planned scenario.'],
                'steps' => ['Open the authorized module and confirm scoped data.', 'Complete the primary end-to-end workflow including evidence and validation.', 'Verify audit, notification, preview/export and negative authorization behavior.', 'Repeat the critical path using keyboard navigation and constrained connectivity.'],
                'expected_result' => 'The journey completes only for the authorized actor, retains immutable evidence and exposes a reproducible decision trail without leaking another county portfolio.',
                'accessibility_needs' => 'Complete with keyboard-only navigation, visible focus, correct labels, announced validation and no loss at 200% zoom.',
                'low_connectivity_variant' => 'Throttle the connection, repeat the critical path and verify recoverable submission, bounded payloads and explicit synchronization state.',
                'required' => true,
                'status' => 'ready',
            ]);
        }
    }
}
