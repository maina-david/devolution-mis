<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessments_pkey');
            $table->uuid('county_id');
            $table->string('cycle', 255);
            $table->string('status', 255)->default('draft');
            $table->decimal('score', 5, 2)->nullable();
            $table->uuid('assessor_id')->nullable();
            $table->timestamp('assessed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('assessment_cycle_id')->nullable();
            $table->uuid('assessment_scorecard_version_id')->nullable();
            $table->decimal('completeness_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('attestation_status', 255)->default('pending');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at', 0)->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at', 0)->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->unique(['county_id', 'assessment_cycle_id'], 'assessments_county_id_assessment_cycle_id_unique');
            $table->unique(['county_id', 'cycle'], 'assessments_county_id_cycle_unique');
            $table->foreign(['approved_by'], 'assessments_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['assessment_cycle_id'], 'assessments_assessment_cycle_id_foreign')
                ->references(['id'])
                ->on('assessment_cycles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_scorecard_version_id'], 'assessments_assessment_scorecard_version_id_foreign')
                ->references(['id'])
                ->on('assessment_scorecard_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessor_id'], 'assessments_assessor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'assessments_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'assessments_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['published_by'], 'assessments_published_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'assessments_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessments_approved_at_index ON public.assessments USING btree (approved_at);
CREATE INDEX assessments_assessed_at_index ON public.assessments USING btree (assessed_at);
CREATE INDEX assessments_attestation_status_index ON public.assessments USING btree (attestation_status);
CREATE INDEX assessments_cycle_index ON public.assessments USING btree (cycle);
CREATE INDEX assessments_published_at_index ON public.assessments USING btree (published_at);
CREATE INDEX assessments_status_index ON public.assessments USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
