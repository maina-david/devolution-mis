<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_case_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary('citizen_case_messages_pkey');
            $table->uuid('citizen_case_id');
            $table->uuid('sender_user_id')->nullable();
            $table->string('direction', 255);
            $table->string('visibility', 255)->default('public');
            $table->string('channel', 255)->default('web');
            $table->text('body');
            $table->string('delivery_status', 255)->default('recorded');
            $table->timestamp('posted_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['citizen_case_id'], 'citizen_case_messages_citizen_case_id_foreign')
                ->references(['id'])
                ->on('citizen_cases')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['sender_user_id'], 'citizen_case_messages_sender_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX citizen_case_messages_citizen_case_id_visibility_posted_at_inde ON public.citizen_case_messages USING btree (citizen_case_id, visibility, posted_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_case_messages');
    }
};
