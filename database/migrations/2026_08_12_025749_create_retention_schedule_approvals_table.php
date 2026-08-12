<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_schedule_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('retention_schedule_id');
            $table->uuid('submitted_by');
            $table->uuid('reviewed_by')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->string('decision', 20)->nullable();
            $table->text('decision_reason')->nullable();
            $table->char('snapshot_checksum', 64);
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->timestampsTz(0);
            $table->unique('retention_schedule_id', 'retention_schedule_approvals_schedule_unique');
            $table->index(['status', 'submitted_at'], 'retention_schedule_approvals_status_submitted_index');
            $table->foreign('retention_schedule_id', 'retention_schedule_approvals_schedule_foreign')
                ->references('id')
                ->on('retention_schedules')
                ->restrictOnDelete();
            $table->foreign('submitted_by', 'retention_schedule_approvals_submitter_foreign')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            $table->foreign('reviewed_by', 'retention_schedule_approvals_reviewer_foreign')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE retention_schedule_approvals
    ADD CONSTRAINT retention_schedule_approvals_status_check
        CHECK (status IN ('submitted', 'approved', 'rejected')),
    ADD CONSTRAINT retention_schedule_approvals_decision_check
        CHECK (
            (status = 'submitted' AND decision IS NULL AND decision_reason IS NULL AND reviewed_by IS NULL AND reviewed_at IS NULL)
            OR
            (status IN ('approved', 'rejected') AND decision = status AND decision_reason IS NOT NULL AND reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL)
        ),
    ADD CONSTRAINT retention_schedule_approvals_actor_separation_check
        CHECK (reviewed_by IS NULL OR reviewed_by <> submitted_by),
    ADD CONSTRAINT retention_schedule_approvals_checksum_check
        CHECK (snapshot_checksum ~ '^[0-9a-f]{64}$');

CREATE OR REPLACE FUNCTION protect_retention_schedule_approval_evidence()
RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'Retention schedule approval evidence cannot be deleted';
    END IF;

    IF OLD.retention_schedule_id IS DISTINCT FROM NEW.retention_schedule_id
        OR OLD.submitted_by IS DISTINCT FROM NEW.submitted_by
        OR OLD.snapshot_checksum IS DISTINCT FROM NEW.snapshot_checksum
        OR OLD.submitted_at IS DISTINCT FROM NEW.submitted_at THEN
        RAISE EXCEPTION 'Retention schedule submission evidence is immutable';
    END IF;

    IF OLD.status IN ('approved', 'rejected') THEN
        RAISE EXCEPTION 'Terminal retention schedule approval evidence is immutable';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER retention_schedule_approval_evidence_guard
BEFORE UPDATE OR DELETE ON retention_schedule_approvals
FOR EACH ROW EXECUTE FUNCTION protect_retention_schedule_approval_evidence();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS protect_retention_schedule_approval_evidence() CASCADE');
        Schema::dropIfExists('retention_schedule_approvals');
    }
};
