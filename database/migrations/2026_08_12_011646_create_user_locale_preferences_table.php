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
        Schema::create('user_locale_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('locale', 5);
            $table->timestamps(0);
            $table->softDeletes('deleted_at', 0);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE user_locale_preferences ADD CONSTRAINT user_locale_preferences_locale_check CHECK (locale IN ('en', 'sw', 'fr'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_locale_preferences');
    }
};
