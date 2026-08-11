<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_forum_meetings', function (Blueprint $table): void {
            $table->uuid('id')->primary('igr_forum_meetings_pkey');
            $table->uuid('igr_forum_id');
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->date('held_on');
            $table->string('venue', 255);
            $table->uuid('chair_user_id')->nullable();
            $table->boolean('quorum_confirmed')->default(false);
            $table->string('minutes_reference', 255);
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['reference'], 'igr_forum_meetings_reference_unique');
            $table->foreign(['chair_user_id'], 'igr_forum_meetings_chair_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'igr_forum_meetings_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['igr_forum_id'], 'igr_forum_meetings_igr_forum_id_foreign')
                ->references(['id'])
                ->on('igr_forums')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_forum_meetings_held_on_index ON public.igr_forum_meetings USING btree (held_on);
CREATE INDEX igr_forum_meetings_igr_forum_id_held_on_index ON public.igr_forum_meetings USING btree (igr_forum_id, held_on);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_forum_meetings');
    }
};
