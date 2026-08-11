<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreement_change_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_agreement_change_requests_pkey');
            $table->uuid('partner_agreement_id');
            $table->smallInteger('version');
            $table->string('change_type', 255);
            $table->jsonb('proposed_changes');
            $table->text('reason');
            $table->date('effective_on');
            $table->uuid('requested_by');
            $table->timestampTz('requested_at', 0);
            $table->char('predecessor_checksum', 64)->nullable();
            $table->char('request_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['partner_agreement_id', 'version'], 'partner_agreement_change_request_version_unique');
            $table->unique(['request_checksum'], 'partner_agreement_change_requests_request_checksum_unique');
            $table->foreign(['partner_agreement_id'], 'partner_agreement_change_requests_partner_agreement_id_foreign')
                ->references(['id'])
                ->on('partner_agreements')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requested_by'], 'partner_agreement_change_requests_requested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_agreement_change_requests_change_type_index ON public.partner_agreement_change_requests USING btree (change_type);
CREATE INDEX partner_agreement_change_requests_effective_on_index ON public.partner_agreement_change_requests USING btree (effective_on);
CREATE TRIGGER partner_agreement_change_requests_immutable BEFORE DELETE OR UPDATE ON partner_agreement_change_requests FOR EACH ROW EXECUTE FUNCTION reject_partner_agreement_change_request_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreement_change_requests');
    }
};
