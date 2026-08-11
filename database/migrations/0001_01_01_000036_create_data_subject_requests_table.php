<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_subject_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('identity_verified_by')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->string('reference', 255);
            $table->string('request_type', 40);
            $table->text('requester_name');
            $table->text('requester_contact');
            $table->string('contact_channel', 20);
            $table->text('scope');
            $table->string('identity_status', 30)->default('pending');
            $table->string('identity_evidence_reference', 255)->nullable();
            $table->string('status', 30)->default('received');
            $table->timestampTz('received_at', 0);
            $table->timestampTz('due_at', 0);
            $table->timestampTz('acknowledged_at', 0)->nullable();
            $table->timestampTz('decided_at', 0)->nullable();
            $table->text('decision')->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('response_evidence_reference', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['reference'], 'data_subject_requests_reference_unique');
            $table->foreign(['assigned_to'], 'data_subject_requests_assigned_to_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['decided_by'], 'data_subject_requests_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['identity_verified_by'], 'data_subject_requests_identity_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX data_subject_requests_request_type_received_at_index ON public.data_subject_requests USING btree (request_type, received_at);
CREATE INDEX data_subject_requests_status_due_at_index ON public.data_subject_requests USING btree (status, due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_requests');
    }
};
