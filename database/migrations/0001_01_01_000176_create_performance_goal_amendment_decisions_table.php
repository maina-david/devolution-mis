<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goal_amendment_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary('performance_goal_amendment_decisions_pkey');
            $table->uuid('performance_goal_amendment_id');
            $table->string('decision', 255);
            $table->text('rationale');
            $table->uuid('decided_by');
            $table->timestampTz('decided_at', 0);
            $table->uuid('applied_version_id')->nullable();
            $table->char('decision_checksum', 64);
            $table->jsonb('decision_snapshot');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['decision_checksum'], 'performance_goal_amendment_decisions_decision_checksum_unique');
            $table->foreign(['applied_version_id'], 'performance_goal_amendment_decisions_applied_version_id_foreign')
                ->references(['id'])
                ->on('performance_goal_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['decided_by'], 'performance_goal_amendment_decisions_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['performance_goal_amendment_id'], 'performance_goal_amendment_decisions_performance_goal_amendment')
                ->references(['id'])
                ->on('performance_goal_amendments')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_goal_amendment_decisions_decided_at_index ON public.performance_goal_amendment_decisions USING btree (decided_at);
CREATE INDEX performance_goal_amendment_decisions_decision_index ON public.performance_goal_amendment_decisions USING btree (decision);
CREATE TRIGGER performance_goal_amendment_decisions_immutable BEFORE DELETE OR UPDATE ON performance_goal_amendment_decisions FOR EACH ROW EXECUTE FUNCTION reject_performance_goal_amendment_decision_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goal_amendment_decisions');
    }
};
