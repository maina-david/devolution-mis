<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->uuid('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_pkey');
            $table->foreign(['permission_id'], 'role_has_permissions_permission_id_foreign')
                ->references(['id'])
                ->on('permissions')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['role_id'], 'role_has_permissions_role_id_foreign')
                ->references(['id'])
                ->on('roles')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
    }
};
