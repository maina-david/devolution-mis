<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_assurance_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary('audit_assurance_runs_pkey');
            $table->string('environment', 40);
            $table->string('outcome', 20);
            $table->bigInteger('event_count');
            $table->bigInteger('verified_event_count');
            $table->bigInteger('legacy_event_count');
            $table->bigInteger('mismatch_count');
            $table->uuid('first_event_id')->nullable();
            $table->uuid('last_event_id')->nullable();
            $table->char('first_event_hash', 64)->nullable();
            $table->char('last_event_hash', 64)->nullable();
            $table->char('chain_root_checksum', 64);
            $table->jsonb('finding_codes');
            $table->string('disk', 255);
            $table->string('path', 255)->nullable();
            $table->string('mime_type', 255);
            $table->bigInteger('size_bytes')->nullable();
            $table->char('artifact_checksum', 64)->nullable();
            $table->string('signature_algorithm', 255)->nullable();
            $table->string('signing_key_reference', 255)->nullable();
            $table->char('signature', 64)->nullable();
            $table->uuid('initiated_by')->nullable();
            $table->string('initiated_by_name', 255);
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0);
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['evidence_checksum'], 'audit_assurance_runs_evidence_checksum_unique');
            $table->foreign(['initiated_by'], 'audit_assurance_runs_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX audit_assurance_runs_environment_index ON public.audit_assurance_runs USING btree (environment);
CREATE INDEX audit_assurance_runs_outcome_index ON public.audit_assurance_runs USING btree (outcome);
CREATE INDEX audit_assurance_runs_outcome_started_at_index ON public.audit_assurance_runs USING btree (outcome, started_at);
CREATE INDEX audit_assurance_runs_started_at_index ON public.audit_assurance_runs USING btree (started_at);
CREATE TRIGGER audit_assurance_runs_immutable BEFORE DELETE OR UPDATE ON audit_assurance_runs FOR EACH ROW EXECUTE FUNCTION protect_audit_assurance_runs();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_assurance_runs');
    }
};
