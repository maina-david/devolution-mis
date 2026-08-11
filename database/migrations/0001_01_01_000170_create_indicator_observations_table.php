<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_observations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('indicator_definition_id');
            $table->uuid('county_id');
            $table->uuid('programme_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('measure_type', 255);
            $table->string('dimension_key', 255)->default('total');
            $table->jsonb('disaggregation')->nullable();
            $table->decimal('numeric_value', 20, 6)->nullable();
            $table->text('narrative_value')->nullable();
            $table->text('source_reference');
            $table->jsonb('provenance');
            $table->string('quality_status', 255)->default('unassessed');
            $table->jsonb('quality_issues')->nullable();
            $table->string('verification_status', 255)->default('submitted');
            $table->uuid('submitted_by');
            $table->uuid('verified_by')->nullable();
            $table->timestamp('submitted_at', 0);
            $table->timestamp('verified_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('source_project_indicator_result_id')->nullable();
            $table->unique(['indicator_definition_id', 'county_id', 'programme_id', 'period_start', 'period_end', 'measure_type', 'dimension_key'], 'indicator_observation_unique');
            $table->unique(['source_project_indicator_result_id'], 'indicator_observations_source_project_indicator_result_id_uniqu');
            $table->foreign(['county_id'], 'indicator_observations_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['indicator_definition_id'], 'indicator_observations_indicator_definition_id_foreign')
                ->references(['id'])
                ->on('indicator_definitions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['programme_id'], 'indicator_observations_programme_id_foreign')
                ->references(['id'])
                ->on('programmes')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['source_project_indicator_result_id'], 'indicator_observations_source_project_indicator_result_id_forei')
                ->references(['id'])
                ->on('project_indicator_results')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'indicator_observations_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'indicator_observations_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX indicator_observations_county_id_period_end_verification_status ON public.indicator_observations USING btree (county_id, period_end, verification_status);
CREATE INDEX indicator_observations_measure_type_index ON public.indicator_observations USING btree (measure_type);
CREATE INDEX indicator_observations_period_end_index ON public.indicator_observations USING btree (period_end);
CREATE INDEX indicator_observations_period_start_index ON public.indicator_observations USING btree (period_start);
CREATE INDEX indicator_observations_quality_status_index ON public.indicator_observations USING btree (quality_status);
CREATE INDEX indicator_observations_submitted_at_index ON public.indicator_observations USING btree (submitted_at);
CREATE INDEX indicator_observations_verification_status_index ON public.indicator_observations USING btree (verification_status);
CREATE INDEX indicator_observations_verified_at_index ON public.indicator_observations USING btree (verified_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_observations');
    }
};
