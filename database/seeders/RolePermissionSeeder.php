<?php

namespace Database\Seeders;

use App\Services\ProgrammeAuthorization;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProgrammeAuthorization $authorization): void
    {
        $authorization->seedMatrix();
    }
}
