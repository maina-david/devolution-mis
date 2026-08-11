<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_cycles_pkey');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestampTz('submission_opens_at', 0)->nullable();
            $table->timestampTz('submission_closes_at', 0)->nullable();
            $table->string('status', 255)->default('planned');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('assessment_scorecard_version_id')->nullable();
            $table->unique(['code'], 'assessment_cycles_code_unique');
            $table->foreign(['assessment_scorecard_version_id'], 'assessment_cycles_assessment_scorecard_version_id_foreign')
                ->references(['id'])
                ->on('assessment_scorecard_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_cycles_name_index ON public.assessment_cycles USING btree (name);
CREATE INDEX assessment_cycles_period_end_index ON public.assessment_cycles USING btree (period_end);
CREATE INDEX assessment_cycles_period_start_index ON public.assessment_cycles USING btree (period_start);
CREATE INDEX assessment_cycles_status_index ON public.assessment_cycles USING btree (status);
CREATE INDEX assessment_cycles_submission_closes_at_index ON public.assessment_cycles USING btree (submission_closes_at);
CREATE INDEX assessment_cycles_submission_opens_at_index ON public.assessment_cycles USING btree (submission_opens_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_cycles');
    }
};
