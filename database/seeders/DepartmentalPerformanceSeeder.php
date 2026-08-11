<?php

namespace Database\Seeders;

use App\Actions\CreatePerformancePlan;
use App\Models\PerformanceCycle;
use App\Models\PerformancePlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepartmentalPerformanceSeeder extends Seeder
{
    public function run(CreatePerformancePlan $createPerformancePlan): void
    {
        if (! app()->isLocal() || PerformancePlan::query()->exists()) {
            return;
        }
        $employee = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $supervisor = User::query()->where('email', 'management@idmis.test')->first();
        if (! $employee || ! $supervisor) {
            return;
        }
        $cycle = PerformanceCycle::query()->updateOrCreate(['code' => 'FY2026-27'], ['name' => 'FY 2026/27 Performance Cycle', 'period_start' => '2026-07-01', 'period_end' => '2027-06-30', 'goal_setting_deadline' => '2026-08-31', 'midterm_review_deadline' => '2027-01-31', 'final_review_deadline' => '2027-06-30', 'status' => 'open', 'created_by' => $employee->id]);
        $createPerformancePlan->handle($employee, ['performance_cycle_id' => $cycle->id, 'supervisor_id' => $supervisor->id, 'organization_id' => null, 'plan_type' => 'individual', 'hris_employee_reference' => 'IPPD-SDD-DEMO-001', 'job_title' => 'Devolution Programme Administrator', 'overall_expectations' => 'Deliver reliable programme coordination, governed reporting and responsive support to national and county stakeholders.', 'goals' => [
            ['code' => 'KPI-01', 'title' => 'Programme reporting timeliness', 'description' => 'Issue validated management reports within the approved reporting calendar.', 'kpi' => 'Reports issued on time', 'unit_of_measure' => 'percent', 'baseline_value' => 70, 'target_value' => 95, 'weight' => 60],
            ['code' => 'KPI-02', 'title' => 'County implementation support', 'description' => 'Complete evidence-backed county implementation support engagements.', 'kpi' => 'Engagements completed', 'unit_of_measure' => 'count', 'baseline_value' => 12, 'target_value' => 20, 'weight' => 40],
        ]]);
    }
}
