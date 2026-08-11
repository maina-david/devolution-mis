<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_level_measurements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('service', 100);
            $table->string('metric', 100);
            $table->decimal('value', 20, 4);
            $table->string('unit', 30);
            $table->decimal('target', 20, 4)->nullable();
            $table->string('status', 20);
            $table->timestampTz('observed_at', 0);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX service_level_measurements_service_metric_observed_at_index ON public.service_level_measurements USING btree (service, metric, observed_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('service_level_measurements');
    }
};
