<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goal_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('performance_goal_id');
            $table->smallInteger('version');
            $table->jsonb('definition_snapshot');
            $table->char('predecessor_checksum', 64)->nullable();
            $table->char('version_checksum', 64);
            $table->uuid('created_by');
            $table->timestampTz('effective_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['performance_goal_id', 'version'], 'performance_goal_versions_performance_goal_id_version_unique');
            $table->unique(['version_checksum'], 'performance_goal_versions_version_checksum_unique');
            $table->foreign(['created_by'], 'performance_goal_versions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['performance_goal_id'], 'performance_goal_versions_performance_goal_id_foreign')
                ->references(['id'])
                ->on('performance_goals')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_goal_versions_effective_at_index ON public.performance_goal_versions USING btree (effective_at);
CREATE TRIGGER performance_goal_versions_immutable BEFORE DELETE OR UPDATE ON performance_goal_versions FOR EACH ROW EXECUTE FUNCTION reject_performance_goal_version_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goal_versions');
    }
};
