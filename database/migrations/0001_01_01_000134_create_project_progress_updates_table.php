<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_progress_updates', function (Blueprint $table): void {
            $table->uuid('id')->primary('project_progress_updates_pkey');
            $table->uuid('devolution_project_id');
            $table->date('reporting_date');
            $table->decimal('physical_progress', 5, 2);
            $table->decimal('financial_progress', 5, 2);
            $table->text('narrative');
            $table->text('achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('next_steps')->nullable();
            $table->jsonb('provenance');
            $table->string('verification_status', 255)->default('submitted');
            $table->uuid('submitted_by');
            $table->uuid('verified_by')->nullable();
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->text('verification_rationale')->nullable();
            $table->unique(['devolution_project_id', 'reporting_date'], 'project_progress_updates_devolution_project_id_reporting_date_u');
            $table->foreign(['devolution_project_id'], 'project_progress_updates_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'project_progress_updates_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'project_progress_updates_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX project_progress_updates_reporting_date_index ON public.project_progress_updates USING btree (reporting_date);
CREATE INDEX project_progress_updates_verification_status_index ON public.project_progress_updates USING btree (verification_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_progress_updates');
    }
};
