<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_meeting_series', function (Blueprint $table): void {
            $table->uuid('id')->primary('dswg_meeting_series_pkey');
            $table->uuid('dswg_working_group_id');
            $table->string('reference_prefix', 255);
            $table->string('title', 255);
            $table->string('frequency', 255);
            $table->smallInteger('interval')->default(DB::raw('\'1\'::smallint'));
            $table->timestampTz('next_occurrence_at', 0);
            $table->date('ends_on');
            $table->smallInteger('duration_minutes');
            $table->string('timezone', 255);
            $table->string('meeting_mode', 255);
            $table->string('venue', 255)->nullable();
            $table->string('virtual_link', 255)->nullable();
            $table->text('agenda');
            $table->smallInteger('quorum_required');
            $table->smallInteger('generation_horizon_days')->default(DB::raw('\'90\'::smallint'));
            $table->integer('next_sequence')->default(1);
            $table->string('status', 255)->default('active');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['reference_prefix'], 'dswg_meeting_series_reference_prefix_unique');
            $table->foreign(['created_by'], 'dswg_meeting_series_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['dswg_working_group_id'], 'dswg_meeting_series_dswg_working_group_id_foreign')
                ->references(['id'])
                ->on('dswg_working_groups')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX dswg_meeting_series_ends_on_index ON public.dswg_meeting_series USING btree (ends_on);
CREATE INDEX dswg_meeting_series_frequency_index ON public.dswg_meeting_series USING btree (frequency);
CREATE INDEX dswg_meeting_series_next_occurrence_at_index ON public.dswg_meeting_series USING btree (next_occurrence_at);
CREATE INDEX dswg_meeting_series_status_index ON public.dswg_meeting_series USING btree (status);
CREATE INDEX dswg_series_generation_index ON public.dswg_meeting_series USING btree (status, next_occurrence_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_meeting_series');
    }
};
