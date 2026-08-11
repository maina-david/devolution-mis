<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_escalations', function (Blueprint $table): void {
            $table->uuid('id')->primary('workflow_escalations_pkey');
            $table->uuid('workflow_instance_id');
            $table->smallInteger('level')->default(DB::raw('\'1\'::smallint'));
            $table->string('reason', 255);
            $table->string('status', 255)->default('open');
            $table->uuid('escalated_to')->nullable();
            $table->timestampTz('due_at', 0)->nullable();
            $table->timestampTz('state_entered_at', 0);
            $table->timestampTz('triggered_at', 0);
            $table->timestampTz('acknowledged_at', 0)->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->jsonb('metadata')->default(DB::raw('\'{}\'::jsonb'));
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['workflow_instance_id', 'reason', 'state_entered_at'], 'workflow_escalations_workflow_instance_id_reason_state_entered_');
            $table->foreign(['escalated_to'], 'workflow_escalations_escalated_to_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'workflow_escalations_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX workflow_escalations_due_at_index ON public.workflow_escalations USING btree (due_at);
CREATE INDEX workflow_escalations_reason_index ON public.workflow_escalations USING btree (reason);
CREATE INDEX workflow_escalations_state_entered_at_index ON public.workflow_escalations USING btree (state_entered_at);
CREATE INDEX workflow_escalations_status_index ON public.workflow_escalations USING btree (status);
CREATE INDEX workflow_escalations_triggered_at_index ON public.workflow_escalations USING btree (triggered_at);
CREATE INDEX workflow_escalations_workflow_instance_id_status_index ON public.workflow_escalations USING btree (workflow_instance_id, status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_escalations');
    }
};
