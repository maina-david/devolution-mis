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
        Schema::create('uat_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reference_data_release_id');
            $table->uuid('created_by');
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->text('objective');
            $table->string('environment', 80);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32)->default('planning');
            $table->jsonb('acceptance_criteria');
            $table->jsonb('required_roles');
            $table->smallInteger('minimum_counties')->default(1);
            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);
            $table->foreign('reference_data_release_id')->references('id')->on('reference_data_releases')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->index(['status', 'starts_on']);
            $table->index(['environment', 'status']);
        });

        DB::statement("ALTER TABLE uat_campaigns ADD CONSTRAINT uat_campaigns_status_check CHECK (status IN ('planning', 'executing', 'review', 'accepted', 'rejected'))");
        DB::statement('ALTER TABLE uat_campaigns ADD CONSTRAINT uat_campaigns_dates_check CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE uat_campaigns ADD CONSTRAINT uat_campaigns_minimum_counties_check CHECK (minimum_counties BETWEEN 1 AND 47)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uat_campaigns');
    }
};
