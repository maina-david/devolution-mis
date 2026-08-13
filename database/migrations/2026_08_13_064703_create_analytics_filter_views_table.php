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
        Schema::create('analytics_filter_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('name', 100);
            $table->jsonb('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps(0);
            $table->softDeletesTz('deleted_at', 0);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });

        DB::statement('CREATE UNIQUE INDEX analytics_filter_views_one_default_per_user ON analytics_filter_views (user_id) WHERE is_default = true AND deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_filter_views');
    }
};
