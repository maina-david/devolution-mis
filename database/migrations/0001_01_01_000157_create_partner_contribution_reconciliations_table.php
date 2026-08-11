<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contribution_reconciliations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('partner_contribution_id');
            $table->smallInteger('version');
            $table->string('decision', 255);
            $table->decimal('verified_committed_amount', 20, 2);
            $table->decimal('verified_disbursed_amount', 20, 2);
            $table->decimal('verified_in_kind_value', 20, 2);
            $table->decimal('disbursement_variance', 20, 2);
            $table->string('source_reference', 255);
            $table->text('review_note');
            $table->uuid('reviewed_by');
            $table->timestampTz('reviewed_at', 0);
            $table->char('evidence_checksum', 64);
            $table->char('predecessor_checksum', 64)->nullable();
            $table->char('decision_checksum', 64);
            $table->jsonb('snapshot');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['partner_contribution_id', 'version'], 'partner_contribution_reconciliation_version_unique');
            $table->unique(['decision_checksum'], 'partner_contribution_reconciliations_decision_checksum_unique');
            $table->foreign(['partner_contribution_id'], 'partner_contribution_reconciliations_partner_contribution_id_fo')
                ->references(['id'])
                ->on('partner_contributions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'partner_contribution_reconciliations_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_contribution_reconciliations_decision_index ON public.partner_contribution_reconciliations USING btree (decision);
CREATE INDEX partner_contribution_reconciliations_reviewed_at_index ON public.partner_contribution_reconciliations USING btree (reviewed_at);
CREATE TRIGGER partner_contribution_reconciliations_immutable BEFORE DELETE OR UPDATE ON partner_contribution_reconciliations FOR EACH ROW EXECUTE FUNCTION reject_partner_contribution_reconciliation_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contribution_reconciliations');
    }
};
