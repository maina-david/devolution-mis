<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_indicator_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_progress_update_id');
            $table->uuid('indicator_definition_id');
            $table->uuid('county_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('dimension_key', 255)->default('total');
            $table->jsonb('disaggregation')->nullable();
            $table->decimal('numeric_value', 24, 6)->nullable();
            $table->text('narrative_value')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['project_progress_update_id', 'indicator_definition_id', 'county_id', 'dimension_key'], 'project_indicator_result_unique');
            $table->foreign(['county_id'], 'project_indicator_results_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['indicator_definition_id'], 'project_indicator_results_indicator_definition_id_foreign')
                ->references(['id'])
                ->on('indicator_definitions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['project_progress_update_id'], 'project_indicator_results_project_progress_update_id_foreign')
                ->references(['id'])
                ->on('project_progress_updates')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX project_indicator_result_lookup ON public.project_indicator_results USING btree (county_id, indicator_definition_id, period_end);
CREATE INDEX project_indicator_results_period_end_index ON public.project_indicator_results USING btree (period_end);
CREATE INDEX project_indicator_results_period_start_index ON public.project_indicator_results USING btree (period_start);
CREATE TRIGGER protect_verified_project_indicator_results_trigger BEFORE DELETE OR UPDATE ON project_indicator_results FOR EACH ROW EXECUTE FUNCTION protect_verified_project_indicator_result();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_indicator_results');
    }
};
