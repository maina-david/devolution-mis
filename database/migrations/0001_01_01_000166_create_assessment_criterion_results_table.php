<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_criterion_results', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_criterion_results_pkey');
            $table->uuid('assessment_id');
            $table->uuid('assessment_criterion_id');
            $table->decimal('submitted_score', 8, 4)->nullable();
            $table->decimal('verified_score', 8, 4)->nullable();
            $table->decimal('override_score', 8, 4)->nullable();
            $table->decimal('weighted_score', 12, 6)->nullable();
            $table->text('submission_rationale')->nullable();
            $table->text('verification_rationale')->nullable();
            $table->text('override_reason')->nullable();
            $table->uuid('scored_by')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->uuid('overridden_by')->nullable();
            $table->timestamp('verified_at', 0)->nullable();
            $table->jsonb('calculation_snapshot')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_id', 'assessment_criterion_id'], 'assessment_criterion_results_assessment_id_assessment_criterion');
            $table->foreign(['assessment_criterion_id'], 'assessment_criterion_results_assessment_criterion_id_foreign')
                ->references(['id'])
                ->on('assessment_criteria')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_id'], 'assessment_criterion_results_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['overridden_by'], 'assessment_criterion_results_overridden_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['scored_by'], 'assessment_criterion_results_scored_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'assessment_criterion_results_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_criterion_results_verified_at_index ON public.assessment_criterion_results USING btree (verified_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_criterion_results');
    }
};
