<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contribution_source_matches', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_contribution_source_matches_pkey');
            $table->uuid('reconciliation_run_id');
            $table->uuid('integration_exchange_id');
            $table->uuid('partner_contribution_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('matched_by')->nullable();
            $table->string('matched_by_name', 255);
            $table->string('external_reference', 255)->nullable();
            $table->string('local_reference', 255)->nullable();
            $table->string('outcome', 40);
            $table->decimal('source_committed_amount', 20, 2)->nullable();
            $table->decimal('source_disbursed_amount', 20, 2)->nullable();
            $table->decimal('source_in_kind_value', 20, 2)->nullable();
            $table->decimal('local_committed_amount', 20, 2)->nullable();
            $table->decimal('local_disbursed_amount', 20, 2)->nullable();
            $table->decimal('local_in_kind_value', 20, 2)->nullable();
            $table->decimal('disbursement_variance', 20, 2)->nullable();
            $table->string('source_currency', 3)->nullable();
            $table->string('local_currency', 3)->nullable();
            $table->char('source_checksum', 64);
            $table->char('match_checksum', 64);
            $table->jsonb('snapshot');
            $table->timestampTz('matched_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['integration_exchange_id'], 'partner_contribution_source_matches_integration_exchange_id_uni');
            $table->unique(['match_checksum'], 'partner_contribution_source_matches_match_checksum_unique');
            $table->foreign(['county_id'], 'partner_contribution_source_matches_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['integration_exchange_id'], 'partner_contribution_source_matches_integration_exchange_id_for')
                ->references(['id'])
                ->on('integration_exchanges')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['matched_by'], 'partner_contribution_source_matches_matched_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['partner_contribution_id'], 'partner_contribution_source_matches_partner_contribution_id_for')
                ->references(['id'])
                ->on('partner_contributions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reconciliation_run_id'], 'partner_contribution_source_matches_reconciliation_run_id_forei')
                ->references(['id'])
                ->on('reconciliation_runs')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_contribution_source_matches_county_id_outcome_index ON public.partner_contribution_source_matches USING btree (county_id, outcome);
CREATE INDEX partner_contribution_source_matches_external_reference_index ON public.partner_contribution_source_matches USING btree (external_reference);
CREATE INDEX partner_contribution_source_matches_local_reference_index ON public.partner_contribution_source_matches USING btree (local_reference);
CREATE INDEX partner_contribution_source_matches_outcome_index ON public.partner_contribution_source_matches USING btree (outcome);
CREATE TRIGGER partner_contribution_source_matches_immutable BEFORE DELETE OR UPDATE ON partner_contribution_source_matches FOR EACH ROW EXECUTE FUNCTION reject_partner_contribution_source_match_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contribution_source_matches');
    }
};
