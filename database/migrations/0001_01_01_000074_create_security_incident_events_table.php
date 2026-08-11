<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incident_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('security_incident_id');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_name', 255);
            $table->string('transition', 40);
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->text('narrative');
            $table->string('evidence_reference', 255)->nullable();
            $table->timestampTz('occurred_at', 0);
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['evidence_checksum'], 'security_incident_events_evidence_checksum_unique');
            $table->foreign(['actor_id'], 'security_incident_events_actor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['security_incident_id'], 'security_incident_events_security_incident_id_foreign')
                ->references(['id'])
                ->on('security_incidents')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX security_incident_events_occurred_at_index ON public.security_incident_events USING btree (occurred_at);
CREATE INDEX security_incident_events_security_incident_id_occurred_at_index ON public.security_incident_events USING btree (security_incident_id, occurred_at);
CREATE TRIGGER security_incident_events_immutable BEFORE DELETE OR UPDATE ON security_incident_events FOR EACH ROW EXECUTE FUNCTION protect_security_incident_events();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incident_events');
    }
};
