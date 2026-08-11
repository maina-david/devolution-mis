<?php

namespace Database\Seeders;

use App\Actions\CreateIgrResolution;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\IgrForum;
use App\Models\IgrResolution;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class IgrResolutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateIgrResolution $createResolution): void
    {
        if (! app()->isLocal()) {
            return;
        }
        $administrator = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        $countyOfficial = User::query()->where('email', 'county.admin@idmis.test')->first();
        $county = County::query()->where('name', 'Mombasa')->first();
        if (! $administrator || ! $countyOfficial || ! $county) {
            return;
        }
        $this->call(IgrWorkflowSeeder::class);
        $forum = IgrForum::query()->firstOrCreate(['code' => 'IGR-CG-SUMMIT'], ['name' => 'National and County Governments Coordinating Summit', 'forum_type' => 'summit', 'mandate' => 'Consultation, cooperation and resolution of matters affecting relations between the two levels of government.', 'secretariat_user_id' => $administrator->id, 'status' => 'active', 'created_by' => $administrator->id]);
        if (IgrResolution::query()->where('resolution_number', 'IGR/SUMMIT/2026/001')->exists()) {
            return;
        }
        $organization = Organization::query()->where('code', 'SDD-DSWG-WASH')->first();
        $createResolution->handle($administrator, ['igr_forum_id' => $forum->id, 'resolution_number' => 'IGR/SUMMIT/2026/001', 'title' => 'Harmonize county conditional grant reporting', 'resolution_text' => 'The State Department and counties shall adopt a common conditional grant reporting and reconciliation schedule.', 'resolved_on' => today()->subMonth()->toDateString(), 'due_on' => today()->addDays(45)->toDateString(), 'priority' => 'high', 'assignments' => [['user_id' => $countyOfficial->id, 'organization_id' => $organization?->id, 'county_id' => $county->id, 'responsibility_role' => 'lead', 'is_lead' => true]]]);
    }
}
