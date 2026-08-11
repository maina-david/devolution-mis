<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary('performance_plans_pkey');
            $table->uuid('performance_cycle_id');
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('employee_id');
            $table->uuid('supervisor_id');
            $table->uuid('organization_id')->nullable();
            $table->string('plan_type', 255)->default('individual');
            $table->string('hris_employee_reference', 255)->nullable();
            $table->string('job_title', 255)->nullable();
            $table->text('overall_expectations');
            $table->string('status', 255)->default('draft');
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('supervisor_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->text('capacity_gap_summary')->nullable();
            $table->string('integration_status', 255)->default('pending');
            $table->jsonb('integration_metadata')->nullable();
            $table->timestamp('submitted_at', 0)->nullable();
            $table->timestamp('decision_due_at', 0)->nullable();
            $table->timestamp('finalized_at', 0)->nullable();
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['performance_cycle_id', 'employee_id', 'plan_type'], 'performance_plans_performance_cycle_id_employee_id_plan_type_un');
            $table->foreign(['created_by'], 'performance_plans_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['employee_id'], 'performance_plans_employee_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['organization_id'], 'performance_plans_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['performance_cycle_id'], 'performance_plans_performance_cycle_id_foreign')
                ->references(['id'])
                ->on('performance_cycles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'performance_plans_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['supervisor_id'], 'performance_plans_supervisor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'performance_plans_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_plans_hris_employee_reference_index ON public.performance_plans USING btree (hris_employee_reference);
CREATE INDEX performance_plans_integration_status_index ON public.performance_plans USING btree (integration_status);
CREATE INDEX performance_plans_plan_type_index ON public.performance_plans USING btree (plan_type);
CREATE INDEX performance_plans_status_decision_due_at_reminder_sent_at_index ON public.performance_plans USING btree (status, decision_due_at, reminder_sent_at);
CREATE INDEX performance_plans_status_index ON public.performance_plans USING btree (status);
CREATE INDEX performance_plans_supervisor_id_status_index ON public.performance_plans USING btree (supervisor_id, status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_plans');
    }
};
