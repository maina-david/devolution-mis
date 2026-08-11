<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary('knowledge_posts_pkey');
            $table->uuid('knowledge_discussion_id');
            $table->uuid('author_id');
            $table->text('body');
            $table->boolean('is_moderated')->default(false);
            $table->timestamp('posted_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->string('moderation_status', 255)->default('visible');
            $table->uuid('moderated_by')->nullable();
            $table->timestamp('moderated_at', 0)->nullable();
            $table->text('moderation_reason')->nullable();
            $table->foreign(['author_id'], 'knowledge_posts_author_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['knowledge_discussion_id'], 'knowledge_posts_knowledge_discussion_id_foreign')
                ->references(['id'])
                ->on('knowledge_discussions')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['moderated_by'], 'knowledge_posts_moderated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."knowledge_posts" ADD CONSTRAINT "knowledge_post_moderation_evidence_check" CHECK (is_moderated = false AND moderation_status::text = 'visible'::text AND moderated_by IS NULL AND moderated_at IS NULL AND moderation_reason IS NULL OR is_moderated = true AND moderated_by IS NOT NULL AND moderated_at IS NOT NULL AND moderation_reason IS NOT NULL);
ALTER TABLE public."knowledge_posts" ADD CONSTRAINT "knowledge_post_moderation_status_check" CHECK (moderation_status::text = ANY (ARRAY['visible'::character varying::text, 'hidden'::character varying::text]));
CREATE INDEX knowledge_posts_moderation_status_index ON public.knowledge_posts USING btree (moderation_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_posts');
    }
};
