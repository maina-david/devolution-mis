<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_scorecard_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_scorecard_versions_pkey');
            $table->uuid('assessment_scorecard_id');
            $table->integer('version');
            $table->string('status', 255)->default('draft');
            $table->text('change_notes')->nullable();
            $table->string('calculation_method', 255)->default('weighted_sum');
            $table->jsonb('mcda_configuration')->default(DB::raw('\'{}\'::jsonb'));
            $table->jsonb('performance_thresholds')->default(DB::raw('\'[]\'::jsonb'));
            $table->char('checksum', 64)->nullable();
            $table->timestampTz('effective_from', 0)->nullable();
            $table->timestampTz('effective_to', 0)->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_scorecard_id', 'version'], 'assessment_scorecard_versions_assessment_scorecard_id_version_u');
            $table->foreign(['assessment_scorecard_id'], 'assessment_scorecard_versions_assessment_scorecard_id_foreign')
                ->references(['id'])
                ->on('assessment_scorecards')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['published_by'], 'assessment_scorecard_versions_published_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_scorecard_versions_checksum_index ON public.assessment_scorecard_versions USING btree (checksum);
CREATE INDEX assessment_scorecard_versions_effective_from_index ON public.assessment_scorecard_versions USING btree (effective_from);
CREATE INDEX assessment_scorecard_versions_effective_to_index ON public.assessment_scorecard_versions USING btree (effective_to);
CREATE INDEX assessment_scorecard_versions_status_index ON public.assessment_scorecard_versions USING btree (status);
CREATE TRIGGER protect_assessment_scorecard_version_trigger BEFORE DELETE OR UPDATE ON assessment_scorecard_versions FOR EACH ROW EXECUTE FUNCTION protect_assessment_scorecard_version();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scorecard_versions');
    }
};
