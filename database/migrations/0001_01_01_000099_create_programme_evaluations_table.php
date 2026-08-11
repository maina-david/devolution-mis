<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary('programme_evaluations_pkey');
            $table->uuid('programme_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('code', 255);
            $table->string('title', 255);
            $table->string('evaluation_type', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 255)->default('planned');
            $table->text('terms_of_reference');
            $table->text('methodology')->nullable();
            $table->text('executive_summary')->nullable();
            $table->jsonb('findings')->nullable();
            $table->jsonb('recommendations')->nullable();
            $table->uuid('lead_evaluator_id')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'programme_evaluations_code_unique');
            $table->foreign(['approved_by'], 'programme_evaluations_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'programme_evaluations_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'programme_evaluations_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['lead_evaluator_id'], 'programme_evaluations_lead_evaluator_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['programme_id'], 'programme_evaluations_programme_id_foreign')
                ->references(['id'])
                ->on('programmes')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'programme_evaluations_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'programme_evaluations_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX programme_evaluations_county_id_status_period_end_index ON public.programme_evaluations USING btree (county_id, status, period_end);
CREATE INDEX programme_evaluations_evaluation_type_index ON public.programme_evaluations USING btree (evaluation_type);
CREATE INDEX programme_evaluations_programme_id_evaluation_type_status_index ON public.programme_evaluations USING btree (programme_id, evaluation_type, status);
CREATE INDEX programme_evaluations_status_index ON public.programme_evaluations USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_evaluations');
    }
};
