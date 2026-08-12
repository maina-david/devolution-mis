<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uat_scenarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('uat_campaign_id');
            $table->uuid('created_by');
            $table->string('code', 80);
            $table->string('module', 80);
            $table->string('title');
            $table->string('actor_role', 80);
            $table->string('priority', 16)->default('normal');
            $table->text('journey');
            $table->jsonb('preconditions');
            $table->jsonb('steps');
            $table->text('expected_result');
            $table->text('accessibility_needs')->nullable();
            $table->text('low_connectivity_variant')->nullable();
            $table->boolean('required')->default(true);
            $table->string('status', 24)->default('ready');
            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['uat_campaign_id', 'code']);
            $table->foreign('uat_campaign_id')->references('id')->on('uat_campaigns')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['uat_campaign_id', 'module', 'status']);
            $table->index(['actor_role', 'status']);
        });

        DB::statement("ALTER TABLE uat_scenarios ADD CONSTRAINT uat_scenarios_priority_check CHECK (priority IN ('critical', 'high', 'normal', 'low'))");
        DB::statement("ALTER TABLE uat_scenarios ADD CONSTRAINT uat_scenarios_status_check CHECK (status IN ('draft', 'ready', 'retired'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uat_scenarios');
    }
};
