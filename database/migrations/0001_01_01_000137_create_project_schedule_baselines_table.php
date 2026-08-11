<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedule_baselines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('devolution_project_id');
            $table->integer('version');
            $table->string('status', 20)->default('pending');
            $table->jsonb('schedule_snapshot');
            $table->jsonb('critical_path_analysis');
            $table->string('snapshot_checksum', 64);
            $table->text('baseline_reason');
            $table->uuid('requested_by');
            $table->uuid('decided_by')->nullable();
            $table->text('decision_rationale')->nullable();
            $table->string('decision_checksum', 64)->nullable();
            $table->timestampTz('decided_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['devolution_project_id', 'version'], 'project_schedule_baselines_devolution_project_id_version_unique');
            $table->foreign(['decided_by'], 'project_schedule_baselines_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['devolution_project_id'], 'project_schedule_baselines_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requested_by'], 'project_schedule_baselines_requested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX project_schedule_baseline_one_pending ON public.project_schedule_baselines USING btree (devolution_project_id) WHERE (((status)::text = 'pending'::text) AND (deleted_at IS NULL));
CREATE INDEX project_schedule_baseline_register_index ON public.project_schedule_baselines USING btree (devolution_project_id, status, created_at);
CREATE TRIGGER project_schedule_baselines_terminal_immutable BEFORE DELETE OR UPDATE ON project_schedule_baselines FOR EACH ROW EXECUTE FUNCTION protect_terminal_project_schedule_baseline();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedule_baselines');
    }
};
