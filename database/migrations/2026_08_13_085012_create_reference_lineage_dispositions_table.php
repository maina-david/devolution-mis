<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_lineage_dispositions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 64)->unique();
            $table->string('record_type', 64);
            $table->uuid('record_id');
            $table->string('decision', 32);
            $table->uuid('reference_data_release_id')->nullable();
            $table->string('successor_record_type', 64)->nullable();
            $table->uuid('successor_record_id')->nullable();
            $table->jsonb('record_snapshot');
            $table->char('record_checksum', 64);
            $table->text('business_reason');
            $table->string('source_reference', 255);
            $table->string('status', 32)->default('proposed');
            $table->uuid('proposed_by');
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->uuid('applied_by')->nullable();
            $table->timestampTz('applied_at', 0)->nullable();
            $table->char('decision_checksum', 64);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('reference_data_release_id')->references('id')->on('reference_data_releases')->restrictOnDelete();
            $table->foreign('proposed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('applied_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['record_type', 'record_id']);
            $table->index(['status', 'created_at']);
            $table->index('reference_data_release_id');
        });

        DB::statement("ALTER TABLE reference_lineage_dispositions ADD CONSTRAINT reference_lineage_dispositions_decision_check CHECK (decision IN ('pin_release', 'retain_legacy', 'deprecate'))");
        DB::statement("ALTER TABLE reference_lineage_dispositions ADD CONSTRAINT reference_lineage_dispositions_status_check CHECK (status IN ('proposed', 'approved', 'rejected', 'applied'))");
        DB::statement("ALTER TABLE reference_lineage_dispositions ADD CONSTRAINT reference_lineage_dispositions_release_check CHECK ((decision = 'pin_release' AND reference_data_release_id IS NOT NULL) OR (decision <> 'pin_release' AND reference_data_release_id IS NULL))");
        DB::statement('ALTER TABLE reference_lineage_dispositions ADD CONSTRAINT reference_lineage_dispositions_successor_check CHECK ((successor_record_type IS NULL AND successor_record_id IS NULL) OR (successor_record_type IS NOT NULL AND successor_record_id IS NOT NULL))');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_applied_reference_lineage_dispositions() RETURNS trigger AS $$
BEGIN
    IF OLD.status IN ('applied', 'rejected') THEN
        RAISE EXCEPTION 'Terminal reference lineage dispositions are immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER reference_lineage_dispositions_terminal_immutable
BEFORE UPDATE OR DELETE ON reference_lineage_dispositions
FOR EACH ROW EXECUTE FUNCTION protect_applied_reference_lineage_dispositions();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_lineage_dispositions');
        DB::statement('DROP FUNCTION IF EXISTS protect_applied_reference_lineage_dispositions()');
    }
};
