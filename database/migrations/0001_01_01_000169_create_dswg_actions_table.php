<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dswg_meeting_id');
            $table->uuid('dswg_decision_id')->nullable();
            $table->uuid('workflow_instance_id')->nullable();
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description');
            $table->uuid('accountable_user_id');
            $table->uuid('accountable_organization_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->date('due_on');
            $table->string('priority', 255)->default('medium');
            $table->string('status', 255)->default('open');
            $table->smallInteger('progress_percentage')->default(DB::raw('\'0\'::smallint'));
            $table->text('progress_note')->nullable();
            $table->text('completion_evidence')->nullable();
            $table->uuid('created_by');
            $table->uuid('completed_by')->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'dswg_actions_code_unique');
            $table->unique(['workflow_instance_id'], 'dswg_actions_workflow_instance_id_unique');
            $table->foreign(['accountable_organization_id'], 'dswg_actions_accountable_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['accountable_user_id'], 'dswg_actions_accountable_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['completed_by'], 'dswg_actions_completed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'dswg_actions_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'dswg_actions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['dswg_decision_id'], 'dswg_actions_dswg_decision_id_foreign')
                ->references(['id'])
                ->on('dswg_decisions')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['dswg_meeting_id'], 'dswg_actions_dswg_meeting_id_foreign')
                ->references(['id'])
                ->on('dswg_meetings')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'dswg_actions_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'dswg_actions_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'dswg_actions_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX dswg_actions_due_on_index ON public.dswg_actions USING btree (due_on);
CREATE INDEX dswg_actions_priority_index ON public.dswg_actions USING btree (priority);
CREATE INDEX dswg_actions_status_due_on_priority_index ON public.dswg_actions USING btree (status, due_on, priority);
CREATE INDEX dswg_actions_status_index ON public.dswg_actions USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_actions');
    }
};
