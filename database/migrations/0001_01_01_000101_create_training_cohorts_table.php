<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_cohorts', function (Blueprint $table): void {
            $table->uuid('id')->primary('training_cohorts_pkey');
            $table->uuid('rollout_wave_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('facilitator_id')->nullable();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->string('audience_role', 255);
            $table->string('delivery_mode', 255);
            $table->string('language', 255);
            $table->string('venue', 255)->nullable();
            $table->smallInteger('seat_capacity');
            $table->decimal('minimum_attendance_hours', 5, 2)->default(DB::raw('\'6\'::numeric'));
            $table->decimal('passing_score', 5, 2)->default(DB::raw('\'70\'::numeric'));
            $table->timestampTz('starts_at', 0);
            $table->timestampTz('ends_at', 0);
            $table->string('status', 255)->default('planned');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'training_cohorts_code_unique');
            $table->foreign(['county_id'], 'training_cohorts_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['facilitator_id'], 'training_cohorts_facilitator_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'training_cohorts_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['rollout_wave_id'], 'training_cohorts_rollout_wave_id_foreign')
                ->references(['id'])
                ->on('rollout_waves')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX training_cohorts_audience_role_index ON public.training_cohorts USING btree (audience_role);
CREATE INDEX training_cohorts_starts_at_index ON public.training_cohorts USING btree (starts_at);
CREATE INDEX training_cohorts_status_index ON public.training_cohorts USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('training_cohorts');
    }
};
