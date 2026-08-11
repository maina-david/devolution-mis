<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_incidents', function (Blueprint $table): void {
            $table->uuid('id')->primary('privacy_incidents_pkey');
            $table->uuid('data_asset_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('reported_by');
            $table->uuid('incident_lead_id');
            $table->uuid('assessed_by')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->string('controller_role', 20);
            $table->string('breach_type', 40);
            $table->text('description');
            $table->jsonb('personal_data_categories');
            $table->bigInteger('estimated_data_subjects')->nullable();
            $table->boolean('contains_sensitive_data')->default(false);
            $table->string('status', 30)->default('reported');
            $table->string('severity', 20)->default('unassessed');
            $table->string('real_risk_of_harm', 20)->default('undetermined');
            $table->timestampTz('occurred_at', 0)->nullable();
            $table->timestampTz('discovered_at', 0);
            $table->timestampTz('controller_notification_due_at', 0)->nullable();
            $table->timestampTz('regulator_notification_due_at', 0);
            $table->timestampTz('contained_at', 0)->nullable();
            $table->timestampTz('assessed_at', 0)->nullable();
            $table->timestampTz('regulator_notified_at', 0)->nullable();
            $table->timestampTz('data_subjects_notified_at', 0)->nullable();
            $table->timestampTz('closed_at', 0)->nullable();
            $table->text('containment_actions')->nullable();
            $table->text('risk_assessment')->nullable();
            $table->string('regulator_notification_reference', 255)->nullable();
            $table->text('regulator_delay_reason')->nullable();
            $table->string('subject_notification_decision', 30)->default('undetermined');
            $table->text('subject_notification_rationale')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('remediation_actions')->nullable();
            $table->string('closure_evidence_reference', 255)->nullable();
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['reference'], 'privacy_incidents_reference_unique');
            $table->foreign(['assessed_by'], 'privacy_incidents_assessed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['closed_by'], 'privacy_incidents_closed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'privacy_incidents_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['data_asset_id'], 'privacy_incidents_data_asset_id_foreign')
                ->references(['id'])
                ->on('data_assets')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['incident_lead_id'], 'privacy_incidents_incident_lead_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reported_by'], 'privacy_incidents_reported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."privacy_incidents" ADD CONSTRAINT "privacy_incidents_risk_check" CHECK (real_risk_of_harm::text = ANY (ARRAY['undetermined'::character varying::text, 'yes'::character varying::text, 'no'::character varying::text]));
ALTER TABLE public."privacy_incidents" ADD CONSTRAINT "privacy_incidents_role_check" CHECK (controller_role::text = ANY (ARRAY['controller'::character varying::text, 'processor'::character varying::text]));
ALTER TABLE public."privacy_incidents" ADD CONSTRAINT "privacy_incidents_severity_check" CHECK (severity::text = ANY (ARRAY['unassessed'::character varying::text, 'low'::character varying::text, 'medium'::character varying::text, 'high'::character varying::text, 'critical'::character varying::text]));
ALTER TABLE public."privacy_incidents" ADD CONSTRAINT "privacy_incidents_status_check" CHECK (status::text = ANY (ARRAY['reported'::character varying::text, 'contained'::character varying::text, 'notification_required'::character varying::text, 'remediation'::character varying::text, 'closed'::character varying::text]));
ALTER TABLE public."privacy_incidents" ADD CONSTRAINT "privacy_incidents_subject_notice_check" CHECK (subject_notification_decision::text = ANY (ARRAY['undetermined'::character varying::text, 'notified'::character varying::text, 'not_required'::character varying::text, 'delayed'::character varying::text]));
ALTER TABLE public."privacy_incidents" ADD CONSTRAINT "privacy_incidents_type_check" CHECK (breach_type::text = ANY (ARRAY['confidentiality'::character varying::text, 'integrity'::character varying::text, 'availability'::character varying::text, 'combined'::character varying::text]));
CREATE INDEX privacy_incidents_county_id_status_index ON public.privacy_incidents USING btree (county_id, status);
CREATE INDEX privacy_incidents_data_asset_id_discovered_at_index ON public.privacy_incidents USING btree (data_asset_id, discovered_at);
CREATE INDEX privacy_incidents_status_regulator_notification_due_at_index ON public.privacy_incidents USING btree (status, regulator_notification_due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_incidents');
    }
};
