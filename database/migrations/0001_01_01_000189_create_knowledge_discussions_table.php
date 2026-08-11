<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_discussions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_item_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('created_by');
            $table->string('title', 255);
            $table->text('prompt');
            $table->string('status', 255)->default('open');
            $table->string('visibility', 255)->default('national');
            $table->timestamp('last_posted_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['county_id'], 'knowledge_discussions_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'knowledge_discussions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['knowledge_item_id'], 'knowledge_discussions_knowledge_item_id_foreign')
                ->references(['id'])
                ->on('knowledge_items')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX knowledge_discussions_status_index ON public.knowledge_discussions USING btree (status);
CREATE INDEX knowledge_discussions_visibility_index ON public.knowledge_discussions USING btree (visibility);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_discussions');
    }
};
