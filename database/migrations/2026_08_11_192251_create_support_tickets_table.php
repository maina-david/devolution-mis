<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('reference_data_release_id');
            $table->uuid('service_desk_policy_id')->nullable();
            $table->char('service_desk_policy_checksum', 64)->nullable();
            $table->uuid('requester_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('reference', 40)->unique();
            $table->string('category', 40);
            $table->string('priority', 20);
            $table->string('channel', 20)->default('web');
            $table->string('subject', 255);
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->text('resolution_summary')->nullable();
            $table->timestampTz('requested_at', 0);
            $table->timestampTz('first_response_due_at', 0);
            $table->timestampTz('resolution_due_at', 0);
            $table->timestampTz('first_responded_at', 0)->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->timestampTz('closed_at', 0)->nullable();
            $table->timestampTz('last_activity_at', 0);
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->foreign('reference_data_release_id')->references('id')->on('reference_data_releases')->restrictOnDelete();
            $table->foreign('service_desk_policy_id')->references('id')->on('service_desk_policies')->restrictOnDelete();
            $table->foreign('requester_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('county_id')->references('id')->on('counties')->restrictOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_category_check CHECK (category IN ('access', 'incident', 'service_request', 'data_quality', 'integration', 'training', 'document', 'other'));
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_priority_check CHECK (priority IN ('low', 'medium', 'high', 'critical'));
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_channel_check CHECK (channel IN ('web', 'email', 'phone', 'walk_in', 'training'));
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_status_check CHECK (status IN ('open', 'triaged', 'in_progress', 'awaiting_requester', 'resolved', 'closed'));
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_sla_order_check CHECK (first_response_due_at <= resolution_due_at);
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_resolved_state_check CHECK ((status IN ('resolved', 'closed')) = (resolved_at IS NOT NULL AND resolved_by IS NOT NULL AND resolution_summary IS NOT NULL));
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_closed_state_check CHECK ((status = 'closed') = (closed_at IS NOT NULL AND closed_by IS NOT NULL));
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_policy_lineage_check CHECK ((service_desk_policy_id IS NULL) = (service_desk_policy_checksum IS NULL));
CREATE INDEX support_tickets_requester_id_index ON support_tickets (requester_id);
CREATE INDEX support_tickets_service_desk_policy_id_index ON support_tickets (service_desk_policy_id);
CREATE INDEX support_tickets_county_id_status_index ON support_tickets (county_id, status);
CREATE INDEX support_tickets_assigned_to_status_index ON support_tickets (assigned_to, status);
CREATE INDEX support_tickets_status_first_response_due_at_index ON support_tickets (status, first_response_due_at);
CREATE INDEX support_tickets_status_resolution_due_at_index ON support_tickets (status, resolution_due_at);
CREATE INDEX support_tickets_requested_at_index ON support_tickets (requested_at);
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
