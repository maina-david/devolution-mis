<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_threats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_id')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->string('stride_category', 30);
            $table->string('asset', 255);
            $table->text('scenario');
            $table->string('threat_actor', 255)->nullable();
            $table->jsonb('entry_points');
            $table->smallInteger('likelihood');
            $table->smallInteger('impact');
            $table->smallInteger('inherent_risk_score');
            $table->jsonb('existing_controls');
            $table->text('treatment_plan');
            $table->string('treatment_status', 30)->default('planned');
            $table->smallInteger('residual_likelihood')->nullable();
            $table->smallInteger('residual_impact')->nullable();
            $table->smallInteger('residual_risk_score')->nullable();
            $table->string('risk_acceptance_reference', 255)->nullable();
            $table->string('status', 30)->default('submitted');
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->date('review_due_at');
            $table->jsonb('evidence_references')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['reference'], 'security_threats_reference_unique');
            $table->foreign(['owner_id'], 'security_threats_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'security_threats_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'security_threats_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX security_threats_status_inherent_risk_score_index ON public.security_threats USING btree (status, inherent_risk_score);
CREATE INDEX security_threats_treatment_status_review_due_at_index ON public.security_threats USING btree (treatment_status, review_due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_threats');
    }
};
