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
        Schema::create('support_ticket_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('support_ticket_id');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_name', 255);
            $table->string('activity_type', 40);
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->text('narrative');
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at', 0);
            $table->char('evidence_checksum', 64)->unique();
            $table->timestampsTz(0);

            $table->foreign('support_ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX support_ticket_activities_ticket_occurred_index ON support_ticket_activities (support_ticket_id, occurred_at);
CREATE INDEX support_ticket_activities_actor_id_index ON support_ticket_activities (actor_id);
CREATE OR REPLACE FUNCTION protect_support_ticket_activities() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'Support ticket activity evidence is immutable';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER support_ticket_activities_immutable BEFORE UPDATE OR DELETE ON support_ticket_activities FOR EACH ROW EXECUTE FUNCTION protect_support_ticket_activities();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_activities');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_support_ticket_activities()');
    }
};
