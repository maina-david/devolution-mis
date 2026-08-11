<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary('reconciliation_runs_pkey');
            $table->uuid('integration_system_id');
            $table->uuid('integration_contract_id')->nullable();
            $table->uuid('initiated_by')->nullable();
            $table->string('reference', 255);
            $table->date('period_from');
            $table->date('period_to');
            $table->bigInteger('source_count')->default(DB::raw('\'0\'::bigint'));
            $table->bigInteger('target_count')->default(DB::raw('\'0\'::bigint'));
            $table->bigInteger('matched_count')->default(DB::raw('\'0\'::bigint'));
            $table->bigInteger('exception_count')->default(DB::raw('\'0\'::bigint'));
            $table->decimal('source_total', 20, 2)->nullable();
            $table->decimal('target_total', 20, 2)->nullable();
            $table->string('status', 30)->default('running');
            $table->char('result_checksum', 64)->nullable();
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['reference'], 'reconciliation_runs_reference_unique');
            $table->foreign(['initiated_by'], 'reconciliation_runs_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['integration_contract_id'], 'reconciliation_runs_integration_contract_id_foreign')
                ->references(['id'])
                ->on('integration_contracts')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['integration_system_id'], 'reconciliation_runs_integration_system_id_foreign')
                ->references(['id'])
                ->on('integration_systems')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX reconciliation_runs_integration_system_id_status_index ON public.reconciliation_runs USING btree (integration_system_id, status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
