<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profile_sector', function (Blueprint $table): void {
            $table->uuid('partner_profile_id');
            $table->uuid('sector_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['partner_profile_id', 'sector_id'], 'partner_profile_sector_pkey');
            $table->foreign(['partner_profile_id'], 'partner_profile_sector_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'partner_profile_sector_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profile_sector');
    }
};
