<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('reported_by');
            $table->uuid('incident_lead_id');
            $table->uuid('closed_by')->nullable();
            $table->string('reference', 255);
            $table->string('record_type', 20);
            $table->string('playbook', 60);
            $table->string('title', 255);
            $table->text('summary');
            $table->jsonb('affected_services');
            $table->string('data_exposure', 30);
            $table->string('severity', 10);
            $table->string('status', 30);
            $table->text('business_impact')->nullable();
            $table->string('external_reference', 255)->nullable();
            $table->jsonb('exercise_objectives')->nullable();
            $table->string('exercise_outcome', 30)->default('not_assessed');
            $table->timestampTz('detected_at', 0);
            $table->timestampTz('acknowledgement_due_at', 0);
            $table->timestampTz('containment_due_at', 0);
            $table->timestampTz('acknowledged_at', 0)->nullable();
            $table->timestampTz('contained_at', 0)->nullable();
            $table->timestampTz('eradicated_at', 0)->nullable();
            $table->timestampTz('recovered_at', 0)->nullable();
            $table->timestampTz('closed_at', 0)->nullable();
            $table->timestampTz('last_transition_at', 0);
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->timestampTz('next_exercise_due_at', 0)->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_actions')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['reference'], 'security_incidents_reference_unique');
            $table->foreign(['closed_by'], 'security_incidents_closed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['incident_lead_id'], 'security_incidents_incident_lead_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reported_by'], 'security_incidents_reported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX security_incidents_detected_at_index ON public.security_incidents USING btree (detected_at);
CREATE INDEX security_incidents_playbook_index ON public.security_incidents USING btree (playbook);
CREATE INDEX security_incidents_record_type_index ON public.security_incidents USING btree (record_type);
CREATE INDEX security_incidents_record_type_status_detected_at_index ON public.security_incidents USING btree (record_type, status, detected_at);
CREATE INDEX security_incidents_severity_index ON public.security_incidents USING btree (severity);
CREATE INDEX security_incidents_status_acknowledgement_due_at_index ON public.security_incidents USING btree (status, acknowledgement_due_at);
CREATE INDEX security_incidents_status_containment_due_at_index ON public.security_incidents USING btree (status, containment_due_at);
CREATE INDEX security_incidents_status_index ON public.security_incidents USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
