<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\County;
use App\Models\RolloutWave;
use App\Models\TrainingCohort;
use App\Models\User;
use App\Support\ReferenceCatalogue;
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
    }
}
