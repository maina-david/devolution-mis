<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary('dswg_meetings_pkey');
            $table->uuid('dswg_working_group_id');
            $table->uuid('workflow_instance_id')->nullable();
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->timestampTz('starts_at', 0);
            $table->timestampTz('ends_at', 0);
            $table->string('meeting_mode', 255);
            $table->string('venue', 255)->nullable();
            $table->string('virtual_link', 255)->nullable();
            $table->text('agenda');
            $table->smallInteger('quorum_required')->default(DB::raw('\'1\'::smallint'));
            $table->string('status', 255)->default('scheduled');
            $table->text('minutes')->nullable();
            $table->uuid('organized_by');
            $table->uuid('minutes_recorded_by')->nullable();
            $table->timestampTz('minutes_recorded_at', 0)->nullable();
            $table->uuid('minutes_approved_by')->nullable();
            $table->timestampTz('minutes_approved_at', 0)->nullable();
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('dswg_meeting_series_id')->nullable();
            $table->integer('occurrence_sequence')->nullable();
            $table->unique(['dswg_meeting_series_id', 'occurrence_sequence'], 'dswg_meeting_series_occurrence_unique');
            $table->unique(['reference'], 'dswg_meetings_reference_unique');
            $table->unique(['workflow_instance_id'], 'dswg_meetings_workflow_instance_id_unique');
            $table->foreign(['dswg_meeting_series_id'], 'dswg_meetings_dswg_meeting_series_id_foreign')
                ->references(['id'])
                ->on('dswg_meeting_series')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['dswg_working_group_id'], 'dswg_meetings_dswg_working_group_id_foreign')
                ->references(['id'])
                ->on('dswg_working_groups')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['minutes_approved_by'], 'dswg_meetings_minutes_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['minutes_recorded_by'], 'dswg_meetings_minutes_recorded_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['organized_by'], 'dswg_meetings_organized_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'dswg_meetings_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX dswg_meeting_schedule_index ON public.dswg_meetings USING btree (dswg_working_group_id, starts_at, status);
CREATE INDEX dswg_meetings_meeting_mode_index ON public.dswg_meetings USING btree (meeting_mode);
CREATE INDEX dswg_meetings_starts_at_index ON public.dswg_meetings USING btree (starts_at);
CREATE INDEX dswg_meetings_status_index ON public.dswg_meetings USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_meetings');
    }
};
