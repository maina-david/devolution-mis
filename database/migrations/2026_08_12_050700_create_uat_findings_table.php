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
        Schema::create('uat_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uat_execution_id');
            $table->uuid('raised_by');
            $table->uuid('owner_id');
            $table->uuid('resolved_by')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->string('severity', 16);
            $table->string('title');
            $table->text('description');
            $table->string('status', 24)->default('open');
            $table->date('due_on');
            $table->text('resolution')->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestampsTz(0);
            $table->foreign('uat_execution_id')->references('id')->on('uat_executions')->restrictOnDelete();
            $table->foreign('raised_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('verified_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['status', 'due_on']);
            $table->index(['owner_id', 'status']);
        });

        DB::statement("ALTER TABLE uat_findings ADD CONSTRAINT uat_findings_severity_check CHECK (severity IN ('critical', 'high', 'medium', 'low'))");
        DB::statement("ALTER TABLE uat_findings ADD CONSTRAINT uat_findings_status_check CHECK (status IN ('open', 'resolved', 'verified', 'reopened'))");
        DB::statement('ALTER TABLE uat_findings ADD CONSTRAINT uat_findings_resolution_actor_check CHECK (resolved_by IS NULL OR resolved_by <> raised_by)');
        DB::statement('ALTER TABLE uat_findings ADD CONSTRAINT uat_findings_verification_actor_check CHECK (verified_by IS NULL OR (verified_by <> raised_by AND verified_by <> resolved_by))');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_uat_finding_delete() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'UAT finding evidence cannot be deleted';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER uat_findings_immutable_delete BEFORE DELETE ON uat_findings FOR EACH ROW EXECUTE FUNCTION prevent_uat_finding_delete();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS uat_findings_immutable_delete ON uat_findings; DROP FUNCTION IF EXISTS prevent_uat_finding_delete();');
        Schema::dropIfExists('uat_findings');
    }
};
