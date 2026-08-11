<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->uuid('service_desk_policy_id')->nullable()->after('reference_data_release_id');
            $table->char('service_desk_policy_checksum', 64)->nullable()->after('service_desk_policy_id');
            $table->foreign('service_desk_policy_id')->references('id')->on('service_desk_policies')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE support_tickets ADD CONSTRAINT support_tickets_policy_lineage_check CHECK ((service_desk_policy_id IS NULL) = (service_desk_policy_checksum IS NULL));
CREATE INDEX support_tickets_service_desk_policy_id_index ON support_tickets (service_desk_policy_id);
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['service_desk_policy_id']);
            $table->dropColumn(['service_desk_policy_id', 'service_desk_policy_checksum']);
        });
    }
};
