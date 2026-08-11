<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_recovery_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary('queue_recovery_attempts_pkey');
            $table->string('failed_job_uuid', 255);
            $table->uuid('initiated_by');
            $table->string('initiated_by_name', 255);
            $table->string('connection', 255);
            $table->string('queue', 255);
            $table->string('job_name', 255);
            $table->char('payload_checksum', 64);
            $table->char('exception_checksum', 64);
            $table->string('outcome', 30);
            $table->string('error_category', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestampTz('failed_at', 0);
            $table->timestampTz('attempted_at', 0);
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['evidence_checksum'], 'queue_recovery_attempts_evidence_checksum_unique');
            $table->foreign(['initiated_by'], 'queue_recovery_attempts_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX queue_recovery_attempts_attempted_at_index ON public.queue_recovery_attempts USING btree (attempted_at);
CREATE INDEX queue_recovery_attempts_failed_job_uuid_attempted_at_index ON public.queue_recovery_attempts USING btree (failed_job_uuid, attempted_at);
CREATE INDEX queue_recovery_attempts_failed_job_uuid_index ON public.queue_recovery_attempts USING btree (failed_job_uuid);
CREATE INDEX queue_recovery_attempts_outcome_index ON public.queue_recovery_attempts USING btree (outcome);
CREATE INDEX queue_recovery_attempts_queue_index ON public.queue_recovery_attempts USING btree (queue);
CREATE TRIGGER queue_recovery_attempts_immutable BEFORE DELETE OR UPDATE ON queue_recovery_attempts FOR EACH ROW EXECUTE FUNCTION protect_queue_recovery_attempts();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_recovery_attempts');
    }
};
