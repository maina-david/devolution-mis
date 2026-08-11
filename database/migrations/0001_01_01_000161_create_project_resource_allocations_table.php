<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_resource_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_resource_id');
            $table->uuid('project_milestone_id');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('planned_units_per_day', 18, 4);
            $table->decimal('planned_units', 18, 4);
            $table->decimal('planned_cost', 18, 2);
            $table->text('notes')->nullable();
            $table->string('allocation_checksum', 64);
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['created_by'], 'project_resource_allocations_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['project_milestone_id'], 'project_resource_allocations_project_milestone_id_foreign')
                ->references(['id'])
                ->on('project_milestones')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['project_resource_id'], 'project_resource_allocations_project_resource_id_foreign')
                ->references(['id'])
                ->on('project_resources')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."project_resource_allocations" ADD CONSTRAINT "project_resource_allocations_cost_nonnegative" CHECK (planned_cost >= 0::numeric);
ALTER TABLE public."project_resource_allocations" ADD CONSTRAINT "project_resource_allocations_period_valid" CHECK (ends_on >= starts_on);
ALTER TABLE public."project_resource_allocations" ADD CONSTRAINT "project_resource_allocations_units_positive" CHECK (planned_units_per_day > 0::numeric AND planned_units > 0::numeric);
CREATE INDEX project_resource_allocations_milestone_index ON public.project_resource_allocations USING btree (project_milestone_id, starts_on);
CREATE INDEX project_resource_allocations_period_index ON public.project_resource_allocations USING btree (project_resource_id, starts_on, ends_on);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_resource_allocations');
    }
};
