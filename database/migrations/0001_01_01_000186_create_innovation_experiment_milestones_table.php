<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('innovation_experiment_milestones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('devolution_innovation_id');
            $table->uuid('owner_id');
            $table->uuid('assessment_document_id')->nullable();
            $table->string('title', 255);
            $table->text('hypothesis');
            $table->string('success_metric', 255);
            $table->string('baseline_value', 255);
            $table->string('target_value', 255);
            $table->date('due_at');
            $table->string('status', 255)->default('planned');
            $table->string('actual_value', 255)->nullable();
            $table->text('outcome_summary')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->timestamp('submitted_at', 0)->nullable();
            $table->string('verification_decision', 255)->default('pending');
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at', 0)->nullable();
            $table->text('verification_rationale')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['assessment_document_id'], 'innovation_experiment_milestones_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['devolution_innovation_id'], 'innovation_experiment_milestones_devolution_innovation_id_forei')
                ->references(['id'])
                ->on('devolution_innovations')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['owner_id'], 'innovation_experiment_milestones_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'innovation_experiment_milestones_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'innovation_experiment_milestones_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."innovation_experiment_milestones" ADD CONSTRAINT "innovation_milestone_decision_evidence_check" CHECK (verification_decision::text = 'pending'::text AND verified_by IS NULL AND verified_at IS NULL AND verification_rationale IS NULL OR (verification_decision::text = ANY (ARRAY['verified'::character varying::text, 'rejected'::character varying::text])) AND verified_by IS NOT NULL AND verified_at IS NOT NULL AND verification_rationale IS NOT NULL);
ALTER TABLE public."innovation_experiment_milestones" ADD CONSTRAINT "innovation_milestone_status_check" CHECK (status::text = ANY (ARRAY['planned'::character varying::text, 'in_progress'::character varying::text, 'completed'::character varying::text, 'failed'::character varying::text]));
ALTER TABLE public."innovation_experiment_milestones" ADD CONSTRAINT "innovation_milestone_submission_check" CHECK ((status::text = ANY (ARRAY['planned'::character varying::text, 'in_progress'::character varying::text])) AND submitted_by IS NULL AND submitted_at IS NULL AND actual_value IS NULL AND outcome_summary IS NULL OR (status::text = ANY (ARRAY['completed'::character varying::text, 'failed'::character varying::text])) AND submitted_by IS NOT NULL AND submitted_at IS NOT NULL AND actual_value IS NOT NULL AND outcome_summary IS NOT NULL AND assessment_document_id IS NOT NULL);
ALTER TABLE public."innovation_experiment_milestones" ADD CONSTRAINT "innovation_milestone_verification_check" CHECK (verification_decision::text = ANY (ARRAY['pending'::character varying::text, 'verified'::character varying::text, 'rejected'::character varying::text]));
CREATE INDEX innovation_milestone_status_due_index ON public.innovation_experiment_milestones USING btree (devolution_innovation_id, status, due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('innovation_experiment_milestones');
    }
};
