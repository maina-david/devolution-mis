<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_migration_rows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_migration_batch_id');
            $table->integer('row_number');
            $table->uuid('county_id')->nullable();
            $table->date('period')->nullable();
            $table->string('metric_code', 100)->nullable();
            $table->string('metric_name', 255)->nullable();
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->text('narrative_value')->nullable();
            $table->string('unit', 80)->nullable();
            $table->string('source_reference', 255)->nullable();
            $table->text('source_payload');
            $table->char('source_checksum', 64);
            $table->string('validation_status', 20);
            $table->jsonb('validation_errors')->default(DB::raw('\'[]\'::jsonb'));
            $table->timestampTz('applied_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['data_migration_batch_id', 'row_number'], 'migration_rows_batch_row_unique');
            $table->foreign(['county_id'], 'data_migration_rows_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['data_migration_batch_id'], 'data_migration_rows_data_migration_batch_id_foreign')
                ->references(['id'])
                ->on('data_migration_batches')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX data_migration_rows_period_index ON public.data_migration_rows USING btree (period);
CREATE INDEX data_migration_rows_validation_status_index ON public.data_migration_rows USING btree (validation_status);
CREATE TRIGGER data_migration_rows_applied_immutable BEFORE DELETE OR UPDATE ON data_migration_rows FOR EACH ROW EXECUTE FUNCTION prevent_applied_data_migration_row_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_migration_rows');
    }
};
