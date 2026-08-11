<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_metrics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_migration_batch_id');
            $table->uuid('data_migration_row_id');
            $table->uuid('county_id');
            $table->string('dataset_type', 40);
            $table->date('period');
            $table->string('metric_code', 100);
            $table->string('metric_name', 255);
            $table->decimal('numeric_value', 18, 4)->nullable();
            $table->text('narrative_value')->nullable();
            $table->string('unit', 80)->nullable();
            $table->string('source_name', 255);
            $table->string('source_reference', 255);
            $table->char('source_checksum', 64);
            $table->char('record_checksum', 64);
            $table->uuid('imported_by');
            $table->timestampTz('imported_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['data_migration_row_id'], 'historical_metrics_data_migration_row_id_unique');
            $table->unique(['record_checksum'], 'historical_metrics_record_checksum_unique');
            $table->foreign(['county_id'], 'historical_metrics_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['data_migration_batch_id'], 'historical_metrics_data_migration_batch_id_foreign')
                ->references(['id'])
                ->on('data_migration_batches')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['data_migration_row_id'], 'historical_metrics_data_migration_row_id_foreign')
                ->references(['id'])
                ->on('data_migration_rows')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['imported_by'], 'historical_metrics_imported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX historical_metric_natural_key_unique ON public.historical_metrics USING btree (dataset_type, county_id, period, lower((metric_code)::text));
CREATE INDEX historical_metrics_dataset_type_index ON public.historical_metrics USING btree (dataset_type);
CREATE INDEX historical_metrics_period_index ON public.historical_metrics USING btree (period);
CREATE TRIGGER historical_metrics_immutable BEFORE DELETE OR UPDATE ON historical_metrics FOR EACH ROW EXECUTE FUNCTION prevent_historical_metric_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_metrics');
    }
};
