<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_exchange_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('integration_exchange_id');
            $table->uuid('initiated_by')->nullable();
            $table->string('initiated_by_name', 255)->nullable();
            $table->smallInteger('attempt_number');
            $table->string('trigger_source', 30);
            $table->string('outcome', 30);
            $table->smallInteger('http_status')->nullable();
            $table->boolean('retryable')->default(false);
            $table->integer('retry_after_seconds')->nullable();
            $table->char('response_checksum', 64)->nullable();
            $table->string('error_category', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0);
            $table->bigInteger('duration_ms');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['integration_exchange_id', 'attempt_number'], 'integration_exchange_attempts_integration_exchange_id_attempt_n');
            $table->foreign(['initiated_by'], 'integration_exchange_attempts_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['integration_exchange_id'], 'integration_exchange_attempts_integration_exchange_id_foreign')
                ->references(['id'])
                ->on('integration_exchanges')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX integration_exchange_attempts_error_category_index ON public.integration_exchange_attempts USING btree (error_category);
CREATE INDEX integration_exchange_attempts_outcome_completed_at_index ON public.integration_exchange_attempts USING btree (outcome, completed_at);
CREATE INDEX integration_exchange_attempts_outcome_index ON public.integration_exchange_attempts USING btree (outcome);
CREATE INDEX integration_exchange_attempts_started_at_index ON public.integration_exchange_attempts USING btree (started_at);
CREATE INDEX integration_exchange_attempts_trigger_source_index ON public.integration_exchange_attempts USING btree (trigger_source);
CREATE TRIGGER integration_exchange_attempts_immutable BEFORE DELETE OR UPDATE ON integration_exchange_attempts FOR EACH ROW EXECUTE FUNCTION protect_integration_exchange_attempts();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_exchange_attempts');
    }
};
