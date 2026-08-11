<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('initiated_by')->nullable();
            $table->uuid('restore_verified_by')->nullable();
            $table->string('reference', 255);
            $table->string('disk', 50);
            $table->string('path', 1000);
            $table->string('database_name', 255);
            $table->string('format', 30)->default('postgres_custom');
            $table->char('sha256', 64)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('status', 30)->default('running');
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestampTz('restore_verified_at', 0)->nullable();
            $table->bigInteger('restore_duration_ms')->nullable();
            $table->integer('verified_table_count')->nullable();
            $table->char('restore_manifest_checksum', 64)->nullable();
            $table->text('error_detail')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['reference'], 'operational_backups_reference_unique');
            $table->foreign(['initiated_by'], 'operational_backups_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['restore_verified_by'], 'operational_backups_restore_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX operational_backups_status_completed_at_index ON public.operational_backups USING btree (status, completed_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_backups');
    }
};
