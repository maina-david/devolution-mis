<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_collaboration_alerts', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_collaboration_alerts_pkey');
            $table->uuid('primary_partner_id');
            $table->uuid('related_partner_id');
            $table->string('alert_type', 255);
            $table->string('severity', 255);
            $table->char('scope_fingerprint', 64);
            $table->jsonb('scope');
            $table->text('summary');
            $table->string('status', 255)->default('open');
            $table->timestampTz('detected_at', 0);
            $table->uuid('resolved_by')->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['scope_fingerprint'], 'partner_collaboration_alerts_scope_fingerprint_unique');
            $table->foreign(['primary_partner_id'], 'partner_collaboration_alerts_primary_partner_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['related_partner_id'], 'partner_collaboration_alerts_related_partner_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['resolved_by'], 'partner_collaboration_alerts_resolved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_collaboration_alerts_alert_type_index ON public.partner_collaboration_alerts USING btree (alert_type);
CREATE INDEX partner_collaboration_alerts_detected_at_index ON public.partner_collaboration_alerts USING btree (detected_at);
CREATE INDEX partner_collaboration_alerts_severity_index ON public.partner_collaboration_alerts USING btree (severity);
CREATE INDEX partner_collaboration_alerts_status_index ON public.partner_collaboration_alerts USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_collaboration_alerts');
    }
};
