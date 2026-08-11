<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('training_cohort_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('participant_reference', 255);
            $table->string('role_title', 255);
            $table->decimal('attended_hours', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('attendance_status', 255)->default('registered');
            $table->string('competency_status', 255)->default('not_assessed');
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['training_cohort_id', 'participant_reference'], 'training_participants_training_cohort_id_participant_reference_');
            $table->unique(['training_cohort_id', 'user_id'], 'training_participants_training_cohort_id_user_id_unique');
            $table->foreign(['county_id'], 'training_participants_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['training_cohort_id'], 'training_participants_training_cohort_id_foreign')
                ->references(['id'])
                ->on('training_cohorts')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'training_participants_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX training_participants_attendance_status_index ON public.training_participants USING btree (attendance_status);
CREATE INDEX training_participants_competency_status_index ON public.training_participants USING btree (competency_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};
