<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_alert_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('operational_alert_id');
            $table->uuid('measurement_id');
            $table->uuid('actor_id')->nullable();
            $table->string('event_type', 30);
            $table->string('status', 20);
            $table->text('narrative');
            $table->timestampTz('occurred_at', 0);
            $table->char('evidence_checksum', 64);
            $table->timestamps();

            $table->foreign('operational_alert_id')->references('id')->on('operational_alerts')->restrictOnDelete();
            $table->foreign('measurement_id')->references('id')->on('service_level_measurements')->restrictOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['operational_alert_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->unique('evidence_checksum');
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_operational_alert_events() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'operational alert event evidence is immutable';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER operational_alert_events_immutable BEFORE UPDATE OR DELETE ON operational_alert_events FOR EACH ROW EXECUTE FUNCTION protect_operational_alert_events();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alert_events');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_operational_alert_events()');
    }
};
