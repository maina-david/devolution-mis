<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_operational_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_operational_alerts_pkey');
            $table->uuid('partner_profile_id');
            $table->uuid('county_id')->nullable();
            $table->string('subject_type', 255)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('alert_type', 255);
            $table->string('severity', 255);
            $table->char('fingerprint', 64);
            $table->text('summary');
            $table->date('due_on')->nullable();
            $table->string('status', 255)->default('open');
            $table->timestampTz('detected_at', 0);
            $table->timestampTz('notified_at', 0)->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['fingerprint'], 'partner_operational_alerts_fingerprint_unique');
            $table->foreign(['county_id'], 'partner_operational_alerts_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['partner_profile_id'], 'partner_operational_alerts_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['resolved_by'], 'partner_operational_alerts_resolved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_operational_alerts_alert_type_index ON public.partner_operational_alerts USING btree (alert_type);
CREATE INDEX partner_operational_alerts_detected_at_index ON public.partner_operational_alerts USING btree (detected_at);
CREATE INDEX partner_operational_alerts_due_on_index ON public.partner_operational_alerts USING btree (due_on);
CREATE INDEX partner_operational_alerts_severity_index ON public.partner_operational_alerts USING btree (severity);
CREATE INDEX partner_operational_alerts_status_index ON public.partner_operational_alerts USING btree (status);
CREATE INDEX partner_operational_alerts_subject_type_subject_id_index ON public.partner_operational_alerts USING btree (subject_type, subject_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_operational_alerts');
    }
};
