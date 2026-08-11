<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profile_user', function (Blueprint $table): void {
            $table->uuid('partner_profile_id');
            $table->uuid('user_id');
            $table->string('relationship_role', 255)->default('member');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['partner_profile_id', 'user_id'], 'partner_profile_user_pkey');
            $table->foreign(['partner_profile_id'], 'partner_profile_user_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'partner_profile_user_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profile_user');
    }
};
