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
        Schema::create('county_uat_campaign', function (Blueprint $table) {
            $table->uuid('uat_campaign_id');
            $table->uuid('county_id');
            $table->string('participation_status', 24)->default('planned');
            $table->text('participation_note')->nullable();
            $table->timestampsTz(0);
            $table->primary(['uat_campaign_id', 'county_id']);
            $table->foreign('uat_campaign_id')->references('id')->on('uat_campaigns')->cascadeOnDelete();
            $table->foreign('county_id')->references('id')->on('counties')->restrictOnDelete();
            $table->index(['county_id', 'participation_status']);
        });

        DB::statement("ALTER TABLE county_uat_campaign ADD CONSTRAINT county_uat_campaign_participation_status_check CHECK (participation_status IN ('planned', 'executed', 'completed', 'withdrawn'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('county_uat_campaign');
    }
};
