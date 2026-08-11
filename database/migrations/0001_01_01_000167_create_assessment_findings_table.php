<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_findings', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_findings_pkey');
            $table->uuid('assessment_id');
            $table->uuid('assessment_criterion_id')->nullable();
            $table->string('code', 255);
            $table->string('severity', 255);
            $table->string('status', 255)->default('open');
            $table->string('title', 255);
            $table->text('description');
            $table->text('county_response')->nullable();
            $table->text('resolution')->nullable();
            $table->uuid('raised_by');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('response_due_at', 0)->nullable();
            $table->timestamp('responded_at', 0)->nullable();
            $table->timestamp('resolved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_id', 'code'], 'assessment_findings_assessment_id_code_unique');
            $table->foreign(['assessment_criterion_id'], 'assessment_findings_assessment_criterion_id_foreign')
                ->references(['id'])
                ->on('assessment_criteria')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_id'], 'assessment_findings_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['assigned_to'], 'assessment_findings_assigned_to_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['raised_by'], 'assessment_findings_raised_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['resolved_by'], 'assessment_findings_resolved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_findings_code_index ON public.assessment_findings USING btree (code);
CREATE INDEX assessment_findings_resolved_at_index ON public.assessment_findings USING btree (resolved_at);
CREATE INDEX assessment_findings_response_due_at_index ON public.assessment_findings USING btree (response_due_at);
CREATE INDEX assessment_findings_severity_index ON public.assessment_findings USING btree (severity);
CREATE INDEX assessment_findings_status_index ON public.assessment_findings USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_findings');
    }
};
