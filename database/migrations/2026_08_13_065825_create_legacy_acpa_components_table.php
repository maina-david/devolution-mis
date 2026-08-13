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
        Schema::create('legacy_acpa_components', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('legacy_acpa_assessment_id');
            $table->uuid('data_migration_batch_id');
            $table->uuid('data_migration_row_id')->unique();
            $table->string('record_type', 40);
            $table->string('record_reference', 150);
            $table->string('criterion_code', 100)->nullable();
            $table->string('title')->nullable();
            $table->decimal('numeric_value', 12, 4)->nullable();
            $table->decimal('maximum_value', 12, 4)->nullable();
            $table->string('status', 50)->nullable();
            $table->string('assignment_role', 100)->nullable();
            $table->text('person_identifier')->nullable();
            $table->string('person_name')->nullable();
            $table->text('description')->nullable();
            $table->text('decision')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->string('file_checksum', 64)->nullable();
            $table->string('source_reference');
            $table->text('source_payload');
            $table->string('source_checksum', 64);
            $table->string('record_checksum', 64)->unique();
            $table->uuid('imported_by');
            $table->timestampTz('imported_at', 0);
            $table->timestamps(0);
            $table->unique(['legacy_acpa_assessment_id', 'record_type', 'record_reference'], 'legacy_acpa_components_natural_key_unique');
            $table->index(['legacy_acpa_assessment_id', 'record_type']);
            $table->index(['criterion_code', 'record_type']);
            $table->foreign('legacy_acpa_assessment_id')->references('id')->on('legacy_acpa_assessments')->restrictOnDelete();
            $table->foreign('data_migration_batch_id')->references('id')->on('data_migration_batches')->restrictOnDelete();
            $table->foreign('data_migration_row_id')->references('id')->on('data_migration_rows')->restrictOnDelete();
            $table->foreign('imported_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('CREATE TRIGGER legacy_acpa_components_immutable BEFORE UPDATE OR DELETE ON legacy_acpa_components FOR EACH ROW EXECUTE FUNCTION prevent_legacy_acpa_mutation()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_acpa_components');
    }
};
