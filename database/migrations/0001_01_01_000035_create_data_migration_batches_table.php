<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 255);
            $table->string('dataset_type', 40);
            $table->string('source_name', 255);
            $table->string('source_reference', 255);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->string('path', 255);
            $table->char('file_checksum', 64);
            $table->string('status', 30)->default('validated');
            $table->integer('total_rows')->default(0);
            $table->integer('valid_rows')->default(0);
            $table->integer('invalid_rows')->default(0);
            $table->jsonb('validation_report')->default(DB::raw('\'{}\'::jsonb'));
            $table->uuid('submitted_by');
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->uuid('applied_by')->nullable();
            $table->timestampTz('applied_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['reference'], 'data_migration_batches_reference_unique');
            $table->foreign(['applied_by'], 'data_migration_batches_applied_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'data_migration_batches_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'data_migration_batches_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX data_migration_batches_dataset_type_index ON public.data_migration_batches USING btree (dataset_type);
CREATE INDEX data_migration_batches_status_index ON public.data_migration_batches USING btree (status);
CREATE TRIGGER data_migration_batches_applied_immutable BEFORE DELETE OR UPDATE ON data_migration_batches FOR EACH ROW EXECUTE FUNCTION prevent_applied_data_migration_batch_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_batches');
    }
};
