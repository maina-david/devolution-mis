<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary('roles_pkey');
            $table->string('name', 255);
            $table->string('guard_name', 255);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
