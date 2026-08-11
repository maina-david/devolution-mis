<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_test_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary('performance_test_runs_pkey');
            $table->string('environment', 40);
            $table->string('tool', 40);
            $table->string('target_url', 2048);
            $table->string('route_path', 255);
            $table->integer('request_count');
            $table->integer('concurrency');
            $table->integer('successful_requests');
            $table->integer('failed_requests');
            $table->decimal('requests_per_second', 12, 3)->nullable();
            $table->decimal('mean_latency_ms', 12, 3)->nullable();
            $table->decimal('p50_latency_ms', 12, 3)->nullable();
            $table->decimal('p95_latency_ms', 12, 3)->nullable();
            $table->decimal('p99_latency_ms', 12, 3)->nullable();
            $table->bigInteger('duration_ms');
            $table->jsonb('threshold_snapshot');
            $table->string('outcome', 20);
            $table->string('error_category', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->uuid('initiated_by')->nullable();
            $table->string('initiated_by_name', 255);
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0);
            $table->char('output_checksum', 64);
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['evidence_checksum'], 'performance_test_runs_evidence_checksum_unique');
            $table->foreign(['initiated_by'], 'performance_test_runs_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_test_runs_environment_index ON public.performance_test_runs USING btree (environment);
CREATE INDEX performance_test_runs_outcome_index ON public.performance_test_runs USING btree (outcome);
CREATE INDEX performance_test_runs_route_path_index ON public.performance_test_runs USING btree (route_path);
CREATE INDEX performance_test_runs_route_path_started_at_index ON public.performance_test_runs USING btree (route_path, started_at);
CREATE INDEX performance_test_runs_started_at_index ON public.performance_test_runs USING btree (started_at);
CREATE TRIGGER performance_test_runs_immutable BEFORE DELETE OR UPDATE ON performance_test_runs FOR EACH ROW EXECUTE FUNCTION protect_performance_test_runs();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_test_runs');
    }
};
