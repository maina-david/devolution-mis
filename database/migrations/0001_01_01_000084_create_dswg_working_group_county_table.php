<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_working_group_county', function (Blueprint $table): void {
            $table->uuid('dswg_working_group_id');
            $table->uuid('county_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['dswg_working_group_id', 'county_id'], 'dswg_working_group_county_pkey');
            $table->foreign(['county_id'], 'dswg_working_group_county_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['dswg_working_group_id'], 'dswg_working_group_county_dswg_working_group_id_foreign')
                ->references(['id'])
                ->on('dswg_working_groups')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_working_group_county');
    }
};
