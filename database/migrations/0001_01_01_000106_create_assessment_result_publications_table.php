<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_result_publications', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_result_publications_pkey');
            $table->uuid('assessment_id');
            $table->uuid('assessment_cycle_id');
            $table->uuid('assessment_scorecard_version_id');
            $table->uuid('county_id');
            $table->decimal('score', 5, 2);
            $table->string('performance_band', 255);
            $table->jsonb('function_profile');
            $table->jsonb('calculation_snapshot');
            $table->string('checksum', 64);
            $table->uuid('published_by');
            $table->timestamp('published_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['assessment_id'], 'assessment_result_publications_assessment_id_unique');
            $table->unique(['checksum'], 'assessment_result_publications_checksum_unique');
            $table->foreign(['assessment_cycle_id'], 'assessment_result_publications_assessment_cycle_id_foreign')
                ->references(['id'])
                ->on('assessment_cycles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_id'], 'assessment_result_publications_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_scorecard_version_id'], 'assessment_result_publications_assessment_scorecard_version_id_')
                ->references(['id'])
                ->on('assessment_scorecard_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'assessment_result_publications_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['published_by'], 'assessment_result_publications_published_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_result_publications_assessment_cycle_id_score_index ON public.assessment_result_publications USING btree (assessment_cycle_id, score);
CREATE INDEX assessment_result_publications_performance_band_index ON public.assessment_result_publications USING btree (performance_band);
CREATE INDEX assessment_result_publications_published_at_index ON public.assessment_result_publications USING btree (published_at);
CREATE TRIGGER protect_assessment_result_publications_trigger BEFORE DELETE OR UPDATE ON assessment_result_publications FOR EACH ROW EXECUTE FUNCTION protect_assessment_result_publication();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_result_publications');
    }
};
