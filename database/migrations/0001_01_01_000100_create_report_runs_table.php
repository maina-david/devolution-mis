<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary('report_runs_pkey');
            $table->uuid('report_schedule_id');
            $table->uuid('triggered_by')->nullable();
            $table->string('status', 255)->default('queued');
            $table->jsonb('filter_snapshot');
            $table->timestampTz('period_from', 0)->nullable();
            $table->timestampTz('period_to', 0)->nullable();
            $table->string('disk', 255)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->integer('record_count')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestampTz('started_at', 0)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->foreign(['report_schedule_id'], 'report_runs_report_schedule_id_foreign')
                ->references(['id'])
                ->on('report_schedules')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['triggered_by'], 'report_runs_triggered_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX report_runs_status_index ON public.report_runs USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
