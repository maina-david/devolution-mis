<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_contracts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('integration_system_id');
            $table->uuid('submitted_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->integer('version');
            $table->string('name', 255);
            $table->string('resource_name', 255);
            $table->string('http_method', 10)->default('POST');
            $table->string('path', 1000);
            $table->jsonb('request_schema');
            $table->jsonb('response_schema')->nullable();
            $table->jsonb('field_mappings')->nullable();
            $table->jsonb('required_headers')->nullable();
            $table->string('idempotency_field', 255)->nullable();
            $table->jsonb('retry_policy');
            $table->integer('rate_limit_per_minute')->default(60);
            $table->string('status', 30)->default('draft');
            $table->char('content_checksum', 64);
            $table->string('source_owner_approval_reference', 255)->nullable();
            $table->string('data_sharing_agreement_reference', 255)->nullable();
            $table->timestampTz('effective_from', 0)->nullable();
            $table->timestampTz('effective_to', 0)->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['integration_system_id', 'version'], 'integration_contracts_integration_system_id_version_unique');
            $table->foreign(['approved_by'], 'integration_contracts_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['integration_system_id'], 'integration_contracts_integration_system_id_foreign')
                ->references(['id'])
                ->on('integration_systems')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'integration_contracts_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX integration_contracts_status_effective_from_effective_to_index ON public.integration_contracts USING btree (status, effective_from, effective_to);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_contracts');
    }
};
