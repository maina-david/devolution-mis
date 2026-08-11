<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_forums', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->string('forum_type', 255);
            $table->text('mandate');
            $table->uuid('secretariat_user_id')->nullable();
            $table->string('status', 255)->default('active');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'igr_forums_code_unique');
            $table->foreign(['created_by'], 'igr_forums_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['secretariat_user_id'], 'igr_forums_secretariat_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_forums_status_index ON public.igr_forums USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_forums');
    }
};
