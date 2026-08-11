<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary('reconciliation_exceptions_pkey');
            $table->uuid('reconciliation_run_id');
            $table->uuid('integration_exchange_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('assigned_to')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->string('local_reference', 255)->nullable();
            $table->string('exception_type', 50);
            $table->string('field_name', 255)->nullable();
            $table->string('severity', 20)->default('medium');
            $table->text('expected_value')->nullable();
            $table->text('actual_value')->nullable();
            $table->text('description');
            $table->string('status', 30)->default('open');
            $table->text('resolution')->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->foreign(['assigned_to'], 'reconciliation_exceptions_assigned_to_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'reconciliation_exceptions_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['integration_exchange_id'], 'reconciliation_exceptions_integration_exchange_id_foreign')
                ->references(['id'])
                ->on('integration_exchanges')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reconciliation_run_id'], 'reconciliation_exceptions_reconciliation_run_id_foreign')
                ->references(['id'])
                ->on('reconciliation_runs')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['resolved_by'], 'reconciliation_exceptions_resolved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX reconciliation_exceptions_status_severity_index ON public.reconciliation_exceptions USING btree (status, severity);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_exceptions');
    }
};
