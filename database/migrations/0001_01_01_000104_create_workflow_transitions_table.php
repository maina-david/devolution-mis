<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id');
            $table->string('transition_name', 255);
            $table->string('from_state', 255)->nullable();
            $table->string('to_state', 255);
            $table->uuid('actor_id')->nullable();
            $table->text('comment')->nullable();
            $table->jsonb('rule_evaluation')->default(DB::raw('\'{}\'::jsonb'));
            $table->jsonb('context_snapshot')->default(DB::raw('\'{}\'::jsonb'));
            $table->timestampTz('occurred_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->foreign(['actor_id'], 'workflow_transitions_actor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'workflow_transitions_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX workflow_transitions_from_state_index ON public.workflow_transitions USING btree (from_state);
CREATE INDEX workflow_transitions_occurred_at_index ON public.workflow_transitions USING btree (occurred_at);
CREATE INDEX workflow_transitions_to_state_index ON public.workflow_transitions USING btree (to_state);
CREATE INDEX workflow_transitions_transition_name_index ON public.workflow_transitions USING btree (transition_name);
CREATE TRIGGER protect_workflow_transition_history_trigger BEFORE DELETE OR UPDATE ON workflow_transitions FOR EACH ROW EXECUTE FUNCTION protect_workflow_transition_history();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
