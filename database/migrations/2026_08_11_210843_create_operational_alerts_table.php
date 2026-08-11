<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('initial_measurement_id');
            $table->uuid('latest_measurement_id');
            $table->uuid('recovery_measurement_id')->nullable();
            $table->string('service', 100);
            $table->string('metric', 100);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->decimal('latest_value', 20, 4);
            $table->decimal('threshold', 20, 4)->nullable();
            $table->string('unit', 30);
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestampTz('first_detected_at', 0);
            $table->timestampTz('last_detected_at', 0);
            $table->uuid('acknowledged_by')->nullable();
            $table->timestampTz('acknowledged_at', 0)->nullable();
            $table->text('acknowledgement_note')->nullable();
            $table->timestampTz('recovered_at', 0)->nullable();
            $table->char('evidence_checksum', 64);
            $table->timestamps();

            $table->foreign('initial_measurement_id')->references('id')->on('service_level_measurements')->restrictOnDelete();
            $table->foreign('latest_measurement_id')->references('id')->on('service_level_measurements')->restrictOnDelete();
            $table->foreign('recovery_measurement_id')->references('id')->on('service_level_measurements')->restrictOnDelete();
            $table->foreign('acknowledged_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['status', 'last_detected_at']);
            $table->index(['service', 'metric', 'last_detected_at']);
            $table->unique('evidence_checksum');
        });

        DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX operational_alerts_one_active_metric_index ON operational_alerts (service, metric) WHERE status IN ('open', 'acknowledged');
CREATE OR REPLACE FUNCTION protect_operational_alert_deletion() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'operational alert evidence cannot be deleted';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER operational_alerts_no_delete BEFORE DELETE ON operational_alerts FOR EACH ROW EXECUTE FUNCTION protect_operational_alert_deletion();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_operational_alert_deletion()');
    }
};
