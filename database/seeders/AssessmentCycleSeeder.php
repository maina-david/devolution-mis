<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AssessmentCycleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AssessmentScorecardSeeder::class);
    }
}
