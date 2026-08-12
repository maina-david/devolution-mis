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
        Schema::create('uat_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uat_scenario_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('tested_by');
            $table->string('environment', 80);
            $table->string('outcome', 24);
            $table->text('actual_result');
            $table->jsonb('evidence_references');
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0);
            $table->char('checksum', 64);
            $table->timestampsTz(0);
            $table->foreign('uat_scenario_id')->references('id')->on('uat_scenarios')->restrictOnDelete();
            $table->foreign('county_id')->references('id')->on('counties')->restrictOnDelete();
            $table->foreign('tested_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['uat_scenario_id', 'county_id', 'completed_at']);
            $table->index(['outcome', 'completed_at']);
        });

        DB::statement("ALTER TABLE uat_executions ADD CONSTRAINT uat_executions_outcome_check CHECK (outcome IN ('pass', 'fail', 'blocked'))");
        DB::statement('ALTER TABLE uat_executions ADD CONSTRAINT uat_executions_time_check CHECK (completed_at >= started_at)');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_uat_execution_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'UAT execution evidence is immutable';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER uat_executions_immutable_update BEFORE UPDATE OR DELETE ON uat_executions FOR EACH ROW EXECUTE FUNCTION prevent_uat_execution_mutation();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS uat_executions_immutable_update ON uat_executions; DROP FUNCTION IF EXISTS prevent_uat_execution_mutation();');
        Schema::dropIfExists('uat_executions');
    }
};
