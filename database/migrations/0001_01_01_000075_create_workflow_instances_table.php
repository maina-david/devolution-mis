<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_version_id');
            $table->string('subject_type', 255)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('current_state', 255);
            $table->string('status', 255)->default('active');
            $table->jsonb('context')->default(DB::raw('\'{}\'::jsonb'));
            $table->uuid('started_by')->nullable();
            $table->timestampTz('started_at', 0);
            $table->timestampTz('state_entered_at', 0);
            $table->timestampTz('due_at', 0)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('business_calendar_id')->nullable();
            $table->foreign(['business_calendar_id'], 'workflow_instances_business_calendar_id_foreign')
                ->references(['id'])
                ->on('business_calendars')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'workflow_instances_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['started_by'], 'workflow_instances_started_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_version_id'], 'workflow_instances_workflow_version_id_foreign')
                ->references(['id'])
                ->on('workflow_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX workflow_instances_completed_at_index ON public.workflow_instances USING btree (completed_at);
CREATE INDEX workflow_instances_county_id_status_due_at_index ON public.workflow_instances USING btree (county_id, status, due_at);
CREATE INDEX workflow_instances_current_state_index ON public.workflow_instances USING btree (current_state);
CREATE INDEX workflow_instances_due_at_index ON public.workflow_instances USING btree (due_at);
CREATE INDEX workflow_instances_started_at_index ON public.workflow_instances USING btree (started_at);
CREATE INDEX workflow_instances_state_entered_at_index ON public.workflow_instances USING btree (state_entered_at);
CREATE INDEX workflow_instances_status_index ON public.workflow_instances USING btree (status);
CREATE INDEX workflow_instances_subject_type_subject_id_index ON public.workflow_instances USING btree (subject_type, subject_id);
CREATE INDEX workflow_instances_workflow_version_id_status_index ON public.workflow_instances USING btree (workflow_version_id, status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
