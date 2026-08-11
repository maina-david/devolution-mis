<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id');
            $table->uuid('user_id');
            $table->string('role', 255);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['team_id', 'user_id'], 'team_members_team_id_user_id_unique');
            $table->foreign(['team_id'], 'team_members_team_id_foreign')
                ->references(['id'])
                ->on('teams')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'team_members_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
