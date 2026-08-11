<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profile_county', function (Blueprint $table): void {
            $table->uuid('partner_profile_id');
            $table->uuid('county_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['partner_profile_id', 'county_id'], 'partner_profile_county_pkey');
            $table->foreign(['county_id'], 'partner_profile_county_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['partner_profile_id'], 'partner_profile_county_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profile_county');
    }
};
