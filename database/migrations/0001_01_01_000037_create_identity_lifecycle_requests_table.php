<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_lifecycle_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_system', 100);
            $table->string('source_event_id', 255);
            $table->string('source_evidence_reference', 500);
            $table->string('source_checksum', 64);
            $table->string('event_type', 20);
            $table->uuid('user_id');
            $table->timestampTz('effective_at', 0);
            $table->jsonb('current_access_snapshot');
            $table->string('proposed_role', 255)->nullable();
            $table->uuid('proposed_home_county_id')->nullable();
            $table->jsonb('proposed_assigned_county_ids')->default(DB::raw('\'[]\'::jsonb'));
            $table->text('business_reason');
            $table->string('status', 32)->default('pending');
            $table->uuid('requested_by');
            $table->uuid('decided_by')->nullable();
            $table->text('decision_rationale')->nullable();
            $table->timestampTz('decided_at', 0)->nullable();
            $table->timestampTz('applied_at', 0)->nullable();
            $table->integer('sessions_revoked')->default(0);
            $table->string('evidence_checksum', 64)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('applied_by')->nullable();
            $table->integer('application_attempts')->default(0);
            $table->timestampTz('last_application_attempt_at', 0)->nullable();
            $table->string('application_error_code', 100)->nullable();
            $table->unique(['source_system', 'source_event_id'], 'identity_lifecycle_requests_source_system_source_event_id_uniqu');
            $table->foreign(['applied_by'], 'identity_lifecycle_requests_applied_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['decided_by'], 'identity_lifecycle_requests_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['proposed_home_county_id'], 'identity_lifecycle_requests_proposed_home_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requested_by'], 'identity_lifecycle_requests_requested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'identity_lifecycle_requests_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX identity_lifecycle_due_application_index ON public.identity_lifecycle_requests USING btree (status, effective_at, application_attempts);
CREATE INDEX identity_lifecycle_requests_status_effective_at_index ON public.identity_lifecycle_requests USING btree (status, effective_at);
CREATE INDEX identity_lifecycle_requests_user_id_created_at_index ON public.identity_lifecycle_requests USING btree (user_id, created_at);
CREATE TRIGGER identity_lifecycle_terminal_immutable BEFORE DELETE OR UPDATE ON identity_lifecycle_requests FOR EACH ROW EXECUTE FUNCTION protect_terminal_identity_lifecycle_request();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_lifecycle_requests');
    }
};
