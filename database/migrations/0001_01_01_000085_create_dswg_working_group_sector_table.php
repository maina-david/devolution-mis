<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_working_group_sector', function (Blueprint $table): void {
            $table->uuid('dswg_working_group_id');
            $table->uuid('sector_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['dswg_working_group_id', 'sector_id'], 'dswg_working_group_sector_pkey');
            $table->foreign(['dswg_working_group_id'], 'dswg_working_group_sector_dswg_working_group_id_foreign')
                ->references(['id'])
                ->on('dswg_working_groups')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'dswg_working_group_sector_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_working_group_sector');
    }
};
