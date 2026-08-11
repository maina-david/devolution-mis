<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_appeals', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_appeals_pkey');
            $table->uuid('assessment_id');
            $table->uuid('assessment_criterion_id')->nullable();
            $table->uuid('appellant_id');
            $table->text('grounds');
            $table->text('requested_remedy');
            $table->string('status', 255)->default('submitted');
            $table->uuid('reviewer_id')->nullable();
            $table->text('decision')->nullable();
            $table->timestamp('submitted_at', 0);
            $table->timestamp('decided_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['appellant_id'], 'assessment_appeals_appellant_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_criterion_id'], 'assessment_appeals_assessment_criterion_id_foreign')
                ->references(['id'])
                ->on('assessment_criteria')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_id'], 'assessment_appeals_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['reviewer_id'], 'assessment_appeals_reviewer_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_appeals_decided_at_index ON public.assessment_appeals USING btree (decided_at);
CREATE INDEX assessment_appeals_status_index ON public.assessment_appeals USING btree (status);
CREATE INDEX assessment_appeals_submitted_at_index ON public.assessment_appeals USING btree (submitted_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_appeals');
    }
};
