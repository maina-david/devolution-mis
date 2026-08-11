<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary('report_schedules_pkey');
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->string('workspace', 255);
            $table->string('format', 255);
            $table->string('frequency', 255);
            $table->jsonb('filters');
            $table->jsonb('recipient_user_ids');
            $table->string('status', 255)->default('draft');
            $table->timestampTz('next_run_at', 0);
            $table->timestampTz('approved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'report_schedules_code_unique');
            $table->foreign(['approved_by'], 'report_schedules_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'report_schedules_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'report_schedules_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'report_schedules_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX report_schedules_next_run_at_index ON public.report_schedules USING btree (next_run_at);
CREATE INDEX report_schedules_status_index ON public.report_schedules USING btree (status);
CREATE INDEX report_schedules_workspace_index ON public.report_schedules USING btree (workspace);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
