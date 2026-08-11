<?php

namespace Database\Seeders;

use App\Models\PerformanceGoal;
use App\Services\PerformanceGoalVersioning;
use Illuminate\Database\Seeder;

class PerformanceGoalVersionBackfillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PerformanceGoalVersioning $versioning): void
    {
        PerformanceGoal::query()
            ->whereDoesntHave('versions')
            ->with('plan.employee')
            ->chunkById(100, function ($goals) use ($versioning): void {
                foreach ($goals as $goal) {
                    $versioning->create($goal, $goal->plan->employee, $versioning->currentSnapshot($goal), $goal->created_at);
                }
            });
    }
}
