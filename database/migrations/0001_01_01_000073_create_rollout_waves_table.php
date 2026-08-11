<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rollout_waves', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('objective');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->smallInteger('planned_participants');
            $table->string('status', 255)->default('planning');
            $table->jsonb('entry_criteria');
            $table->jsonb('support_channels');
            $table->boolean('help_desk_rehearsed')->default(false);
            $table->boolean('training_materials_approved')->default(false);
            $table->text('readiness_notes')->nullable();
            $table->timestampTz('approved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'rollout_waves_code_unique');
            $table->foreign(['approved_by'], 'rollout_waves_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'rollout_waves_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'rollout_waves_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX rollout_waves_ends_on_index ON public.rollout_waves USING btree (ends_on);
CREATE INDEX rollout_waves_starts_on_index ON public.rollout_waves USING btree (starts_on);
CREATE INDEX rollout_waves_status_index ON public.rollout_waves USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('rollout_waves');
    }
};
