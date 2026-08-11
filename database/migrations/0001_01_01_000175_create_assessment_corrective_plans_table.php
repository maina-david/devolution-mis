<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_corrective_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('county_id');
            $table->uuid('assessment_finding_id')->nullable();
            $table->uuid('assessment_appeal_id')->nullable();
            $table->uuid('submitted_by');
            $table->uuid('reviewed_by')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->text('root_cause');
            $table->text('expected_outcome');
            $table->string('status', 30)->default('submitted');
            $table->date('due_at');
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->text('review_note')->nullable();
            $table->timestampTz('closure_requested_at', 0)->nullable();
            $table->timestampTz('closed_at', 0)->nullable();
            $table->text('closure_decision')->nullable();
            $table->string('checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_appeal_id'], 'assessment_corrective_plans_assessment_appeal_id_unique');
            $table->unique(['assessment_finding_id'], 'assessment_corrective_plans_assessment_finding_id_unique');
            $table->unique(['reference'], 'assessment_corrective_plans_reference_unique');
            $table->foreign(['assessment_appeal_id'], 'assessment_corrective_plans_assessment_appeal_id_foreign')
                ->references(['id'])
                ->on('assessment_appeals')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_finding_id'], 'assessment_corrective_plans_assessment_finding_id_foreign')
                ->references(['id'])
                ->on('assessment_findings')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_id'], 'assessment_corrective_plans_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['closed_by'], 'assessment_corrective_plans_closed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'assessment_corrective_plans_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'assessment_corrective_plans_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'assessment_corrective_plans_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."assessment_corrective_plans" ADD CONSTRAINT "assessment_corrective_plans_one_source" CHECK (((assessment_finding_id IS NOT NULL)::integer + (assessment_appeal_id IS NOT NULL)::integer) = 1);
CREATE INDEX assessment_corrective_plans_assessment_id_status_index ON public.assessment_corrective_plans USING btree (assessment_id, status);
CREATE INDEX assessment_corrective_plans_county_id_status_due_at_index ON public.assessment_corrective_plans USING btree (county_id, status, due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_corrective_plans');
    }
};
