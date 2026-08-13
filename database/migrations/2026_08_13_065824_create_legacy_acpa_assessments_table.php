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
        Schema::create('legacy_acpa_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_migration_batch_id');
            $table->uuid('data_migration_row_id')->unique();
            $table->uuid('county_id');
            $table->string('assessment_reference', 150);
            $table->date('period');
            $table->string('cycle_name', 255);
            $table->string('status', 50);
            $table->decimal('overall_score', 10, 4)->nullable();
            $table->string('source_name');
            $table->string('source_reference');
            $table->string('source_checksum', 64);
            $table->string('record_checksum', 64)->unique();
            $table->uuid('imported_by');
            $table->timestampTz('imported_at', 0);
            $table->timestamps(0);
            $table->unique(['county_id', 'assessment_reference']);
            $table->index(['county_id', 'period']);
            $table->index(['status', 'period']);
            $table->foreign('data_migration_batch_id')->references('id')->on('data_migration_batches')->restrictOnDelete();
            $table->foreign('data_migration_row_id')->references('id')->on('data_migration_rows')->restrictOnDelete();
            $table->foreign('county_id')->references('id')->on('counties')->restrictOnDelete();
            $table->foreign('imported_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_legacy_acpa_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Applied legacy ACPA records are immutable';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER legacy_acpa_assessments_immutable BEFORE UPDATE OR DELETE ON legacy_acpa_assessments FOR EACH ROW EXECUTE FUNCTION prevent_legacy_acpa_mutation();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_acpa_assessments');
        DB::statement('DROP FUNCTION IF EXISTS prevent_legacy_acpa_mutation()');
    }
};
