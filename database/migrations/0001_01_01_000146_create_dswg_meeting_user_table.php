<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_meeting_user', function (Blueprint $table): void {
            $table->uuid('dswg_meeting_id');
            $table->uuid('user_id');
            $table->string('invitation_status', 255)->default('pending');
            $table->string('attendance_status', 255)->default('not_recorded');
            $table->string('meeting_role', 255)->default('participant');
            $table->timestampTz('invited_at', 0);
            $table->timestampTz('responded_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['dswg_meeting_id', 'user_id'], 'dswg_meeting_user_pkey');
            $table->foreign(['dswg_meeting_id'], 'dswg_meeting_user_dswg_meeting_id_foreign')
                ->references(['id'])
                ->on('dswg_meetings')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'dswg_meeting_user_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX dswg_meeting_user_attendance_status_index ON public.dswg_meeting_user USING btree (attendance_status);
CREATE INDEX dswg_meeting_user_invitation_status_index ON public.dswg_meeting_user USING btree (invitation_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_meeting_user');
    }
};
