<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('county_user', function (Blueprint $table): void {
            $table->uuid('county_id');
            $table->uuid('user_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['county_id', 'user_id'], 'county_user_county_id_user_id_unique');
            $table->foreign(['county_id'], 'county_user_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'county_user_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('county_user');
    }
};
