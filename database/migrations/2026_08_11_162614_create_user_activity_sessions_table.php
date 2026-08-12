<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_activity_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->char('session_fingerprint', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('current_route')->nullable();
            $table->text('current_path')->nullable();
            $table->string('current_page_title')->nullable();
            $table->string('last_method', 10)->nullable();
            $table->string('last_action')->nullable();
            $table->timestampTz('logged_in_at');
            $table->timestampTz('last_seen_at')->index();
            $table->timestampTz('logged_out_at')->nullable()->index();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['user_id', 'logged_in_at']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_sessions');
    }
};
