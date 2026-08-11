<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_collaboration_action_update_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_collaboration_action_update_decisions_pkey');
            $table->uuid('partner_collaboration_action_update_id');
            $table->string('decision', 255);
            $table->text('verification_note');
            $table->uuid('verified_by');
            $table->timestampTz('verified_at', 0);
            $table->char('decision_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['partner_collaboration_action_update_id'], 'partner_action_update_decision_unique');
            $table->unique(['decision_checksum'], 'partner_collaboration_action_update_decisions_decision_checksum');
            $table->foreign(['partner_collaboration_action_update_id'], 'partner_collaboration_action_update_decisions_partner_collabora')
                ->references(['id'])
                ->on('partner_collaboration_action_updates')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'partner_collaboration_action_update_decisions_verified_by_forei')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_collaboration_action_update_decisions_decision_index ON public.partner_collaboration_action_update_decisions USING btree (decision);
CREATE TRIGGER partner_action_update_decisions_immutable BEFORE DELETE OR UPDATE ON partner_collaboration_action_update_decisions FOR EACH ROW EXECUTE FUNCTION reject_partner_action_update_decision_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_collaboration_action_update_decisions');
    }
};
