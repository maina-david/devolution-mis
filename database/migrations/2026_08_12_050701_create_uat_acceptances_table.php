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
        Schema::create('uat_acceptances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uat_campaign_id');
            $table->uuid('submitted_by');
            $table->uuid('decided_by')->nullable();
            $table->string('decision', 24)->default('pending');
            $table->jsonb('criteria_snapshot');
            $table->jsonb('coverage_snapshot');
            $table->unsignedInteger('open_findings_count');
            $table->char('checksum', 64);
            $table->text('decision_reason')->nullable();
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('decided_at', 0)->nullable();
            $table->timestampsTz(0);
            $table->foreign('uat_campaign_id')->references('id')->on('uat_campaigns')->restrictOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('decided_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['decision', 'submitted_at']);
            $table->index(['uat_campaign_id', 'submitted_at']);
        });

        DB::statement("ALTER TABLE uat_acceptances ADD CONSTRAINT uat_acceptances_decision_check CHECK (decision IN ('pending', 'accepted', 'rejected'))");
        DB::statement('ALTER TABLE uat_acceptances ADD CONSTRAINT uat_acceptances_actor_check CHECK (decided_by IS NULL OR decided_by <> submitted_by)');
        DB::statement("CREATE UNIQUE INDEX uat_acceptances_one_pending_per_campaign ON uat_acceptances (uat_campaign_id) WHERE decision = 'pending'");
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_terminal_uat_acceptance_mutation() RETURNS trigger AS $$
BEGIN
    IF OLD.decision IN ('accepted', 'rejected') THEN
        RAISE EXCEPTION 'Terminal UAT acceptance evidence is immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'UAT acceptance evidence cannot be deleted';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER uat_acceptances_terminal_immutable BEFORE UPDATE OR DELETE ON uat_acceptances FOR EACH ROW EXECUTE FUNCTION prevent_terminal_uat_acceptance_mutation();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS uat_acceptances_terminal_immutable ON uat_acceptances; DROP FUNCTION IF EXISTS prevent_terminal_uat_acceptance_mutation();');
        Schema::dropIfExists('uat_acceptances');
    }
};
