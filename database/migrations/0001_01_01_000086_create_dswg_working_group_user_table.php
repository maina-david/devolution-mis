<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_working_group_user', function (Blueprint $table): void {
            $table->uuid('dswg_working_group_id');
            $table->uuid('user_id');
            $table->string('membership_role', 255)->default('member');
            $table->string('status', 255)->default('active');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['dswg_working_group_id', 'user_id'], 'dswg_working_group_user_pkey');
            $table->foreign(['dswg_working_group_id'], 'dswg_working_group_user_dswg_working_group_id_foreign')
                ->references(['id'])
                ->on('dswg_working_groups')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'dswg_working_group_user_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_working_group_user');
    }
};
