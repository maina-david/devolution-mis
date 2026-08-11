<?php

namespace Database\Seeders;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class KnowledgeAnalyticsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate(ProgrammePermission::ViewKnowledgeAnalytics->value, 'web');

        foreach ([UserRole::CountyAdmin, UserRole::Assessor, UserRole::DevelopmentPartner, UserRole::TopManagement, UserRole::DevolutionAdmin, UserRole::PlatformAdmin] as $userRole) {
            Role::findOrCreate($userRole->value, 'web')->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
