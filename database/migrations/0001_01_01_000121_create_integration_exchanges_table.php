<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_exchanges', function (Blueprint $table): void {
            $table->uuid('id')->primary('integration_exchanges_pkey');
            $table->uuid('integration_contract_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->string('direction', 20);
            $table->uuid('correlation_id');
            $table->string('external_reference', 255)->nullable();
            $table->string('idempotency_key', 255);
            $table->text('request_payload');
            $table->text('response_payload')->nullable();
            $table->jsonb('request_headers')->nullable();
            $table->char('payload_checksum', 64);
            $table->string('status', 30)->default('accepted');
            $table->smallInteger('http_status')->nullable();
            $table->smallInteger('attempt_count')->default(DB::raw('\'0\'::smallint'));
            $table->timestampTz('next_attempt_at', 0)->nullable();
            $table->timestampTz('source_occurred_at', 0)->nullable();
            $table->timestampTz('accepted_at', 0);
            $table->timestampTz('processed_at', 0)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->string('error_category', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->uuid('oauth_client_id')->nullable();
            $table->unique(['idempotency_key'], 'integration_exchanges_idempotency_key_unique');
            $table->foreign(['county_id'], 'integration_exchanges_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'integration_exchanges_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['integration_contract_id'], 'integration_exchanges_integration_contract_id_foreign')
                ->references(['id'])
                ->on('integration_contracts')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['oauth_client_id'], 'integration_exchanges_oauth_client_id_foreign')
                ->references(['id'])
                ->on('oauth_clients')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX integration_exchanges_correlation_id_index ON public.integration_exchanges USING btree (correlation_id);
CREATE INDEX integration_exchanges_external_reference_index ON public.integration_exchanges USING btree (external_reference);
CREATE INDEX integration_exchanges_status_next_attempt_at_index ON public.integration_exchanges USING btree (status, next_attempt_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_exchanges');
    }
};
