<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('county_rollout_wave', function (Blueprint $table): void {
            $table->uuid('rollout_wave_id');
            $table->uuid('county_id');
            $table->string('readiness_status', 255)->default('planned');
            $table->text('readiness_note')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['rollout_wave_id', 'county_id'], 'county_rollout_wave_pkey');
            $table->foreign(['county_id'], 'county_rollout_wave_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['rollout_wave_id'], 'county_rollout_wave_rollout_wave_id_foreign')
                ->references(['id'])
                ->on('rollout_waves')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('county_rollout_wave');
    }
};
