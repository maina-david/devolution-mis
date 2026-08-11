<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->uuid('id')->primary('teams_pkey');
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->boolean('is_personal')->default(false);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['slug'], 'teams_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
