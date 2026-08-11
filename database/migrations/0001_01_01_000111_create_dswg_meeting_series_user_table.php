<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_meeting_series_user', function (Blueprint $table): void {
            $table->uuid('dswg_meeting_series_id');
            $table->uuid('user_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['dswg_meeting_series_id', 'user_id'], 'dswg_meeting_series_user_pkey');
            $table->foreign(['dswg_meeting_series_id'], 'dswg_meeting_series_user_dswg_meeting_series_id_foreign')
                ->references(['id'])
                ->on('dswg_meeting_series')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'dswg_meeting_series_user_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_meeting_series_user');
    }
};
