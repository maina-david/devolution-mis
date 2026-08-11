<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreement_change_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_agreement_change_decisions_pkey');
            $table->uuid('partner_agreement_change_request_id');
            $table->string('decision', 255);
            $table->text('decision_note');
            $table->uuid('decided_by');
            $table->timestampTz('decided_at', 0);
            $table->char('evidence_checksum', 64);
            $table->char('decision_checksum', 64);
            $table->jsonb('snapshot');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['decision_checksum'], 'partner_agreement_change_decisions_decision_checksum_unique');
            $table->unique(['partner_agreement_change_request_id'], 'partner_change_decision_request_unique');
            $table->foreign(['decided_by'], 'partner_agreement_change_decisions_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['partner_agreement_change_request_id'], 'partner_change_decision_request_fk')
                ->references(['id'])
                ->on('partner_agreement_change_requests')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_agreement_change_decisions_decision_index ON public.partner_agreement_change_decisions USING btree (decision);
CREATE TRIGGER partner_agreement_change_decisions_immutable BEFORE DELETE OR UPDATE ON partner_agreement_change_decisions FOR EACH ROW EXECUTE FUNCTION reject_partner_agreement_change_decision_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreement_change_decisions');
    }
};
