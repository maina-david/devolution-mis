<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goal_amendments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('performance_plan_id');
            $table->uuid('performance_goal_id');
            $table->uuid('base_version_id');
            $table->smallInteger('request_version');
            $table->jsonb('proposed_snapshot');
            $table->text('reason');
            $table->uuid('requested_by');
            $table->timestampTz('requested_at', 0);
            $table->char('predecessor_checksum', 64)->nullable();
            $table->char('request_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['performance_plan_id', 'request_version'], 'performance_goal_amendments_performance_plan_id_request_version');
            $table->unique(['request_checksum'], 'performance_goal_amendments_request_checksum_unique');
            $table->foreign(['base_version_id'], 'performance_goal_amendments_base_version_id_foreign')
                ->references(['id'])
                ->on('performance_goal_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['performance_goal_id'], 'performance_goal_amendments_performance_goal_id_foreign')
                ->references(['id'])
                ->on('performance_goals')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['performance_plan_id'], 'performance_goal_amendments_performance_plan_id_foreign')
                ->references(['id'])
                ->on('performance_plans')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requested_by'], 'performance_goal_amendments_requested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_goal_amendments_performance_goal_id_requested_at_in ON public.performance_goal_amendments USING btree (performance_goal_id, requested_at);
CREATE INDEX performance_goal_amendments_requested_at_index ON public.performance_goal_amendments USING btree (requested_at);
CREATE TRIGGER performance_goal_amendments_immutable BEFORE DELETE OR UPDATE ON performance_goal_amendments FOR EACH ROW EXECUTE FUNCTION reject_performance_goal_amendment_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goal_amendments');
    }
};
