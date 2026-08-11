<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary('team_invitations_pkey');
            $table->string('code', 64);
            $table->uuid('team_id');
            $table->string('email', 255);
            $table->string('role', 255);
            $table->uuid('invited_by');
            $table->timestamp('expires_at', 0)->nullable();
            $table->timestamp('accepted_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'team_invitations_code_unique');
            $table->foreign(['invited_by'], 'team_invitations_invited_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['team_id'], 'team_invitations_team_id_foreign')
                ->references(['id'])
                ->on('teams')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
