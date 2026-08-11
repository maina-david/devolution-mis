<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_collaboration_action_updates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('partner_collaboration_action_id');
            $table->decimal('progress_percentage', 5, 2);
            $table->text('narrative');
            $table->uuid('submitted_by');
            $table->timestampTz('submitted_at', 0);
            $table->char('evidence_checksum', 64)->nullable();
            $table->char('update_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['update_checksum'], 'partner_collaboration_action_updates_update_checksum_unique');
            $table->foreign(['partner_collaboration_action_id'], 'partner_collaboration_action_updates_partner_collaboration_acti')
                ->references(['id'])
                ->on('partner_collaboration_actions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'partner_collaboration_action_updates_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER partner_collaboration_action_updates_immutable BEFORE DELETE OR UPDATE ON partner_collaboration_action_updates FOR EACH ROW EXECUTE FUNCTION reject_partner_collaboration_action_update_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_collaboration_action_updates');
    }
};
