<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_offline_syncs', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_offline_syncs_pkey');
            $table->uuid('learning_offline_package_id');
            $table->uuid('learning_enrollment_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('submitted_by');
            $table->string('submitted_by_name', 255);
            $table->uuid('reviewed_by')->nullable();
            $table->string('reviewed_by_name', 255)->nullable();
            $table->uuid('client_sync_id');
            $table->uuid('device_id');
            $table->string('schema_version', 80);
            $table->string('status', 30)->default('pending');
            $table->jsonb('payload');
            $table->char('payload_checksum', 64);
            $table->char('base_progress_checksum', 64);
            $table->char('decision_checksum', 64)->nullable();
            $table->smallInteger('event_count');
            $table->text('decision_reason')->nullable();
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->timestampTz('applied_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['learning_enrollment_id', 'client_sync_id'], 'learning_offline_syncs_learning_enrollment_id_client_sync_id_un');
            $table->foreign(['county_id'], 'learning_offline_syncs_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_enrollment_id'], 'learning_offline_syncs_learning_enrollment_id_foreign')
                ->references(['id'])
                ->on('learning_enrollments')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_offline_package_id'], 'learning_offline_syncs_learning_offline_package_id_foreign')
                ->references(['id'])
                ->on('learning_offline_packages')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'learning_offline_syncs_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'learning_offline_syncs_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_offline_syncs_applied_at_index ON public.learning_offline_syncs USING btree (applied_at);
CREATE INDEX learning_offline_syncs_county_id_status_submitted_at_index ON public.learning_offline_syncs USING btree (county_id, status, submitted_at);
CREATE INDEX learning_offline_syncs_reviewed_at_index ON public.learning_offline_syncs USING btree (reviewed_at);
CREATE INDEX learning_offline_syncs_status_index ON public.learning_offline_syncs USING btree (status);
CREATE INDEX learning_offline_syncs_submitted_at_index ON public.learning_offline_syncs USING btree (submitted_at);
CREATE TRIGGER learning_offline_syncs_immutable BEFORE DELETE OR UPDATE ON learning_offline_syncs FOR EACH ROW EXECUTE FUNCTION protect_learning_offline_sync_evidence();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_offline_syncs');
    }
};
