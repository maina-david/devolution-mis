<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('assessment_document_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('sector_id')->nullable();
            $table->uuid('author_id');
            $table->string('reference', 255);
            $table->string('item_type', 255);
            $table->string('title', 255);
            $table->text('summary');
            $table->text('content_body')->nullable();
            $table->jsonb('tags');
            $table->string('visibility', 255)->default('national');
            $table->string('status', 255)->default('draft');
            $table->date('published_on')->nullable();
            $table->timestamp('review_due_at', 0)->nullable();
            $table->string('source_organization', 255)->nullable();
            $table->string('external_url', 255)->nullable();
            $table->string('language', 255);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['reference'], 'knowledge_items_reference_unique');
            $table->foreign(['assessment_document_id'], 'knowledge_items_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['author_id'], 'knowledge_items_author_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'knowledge_items_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'knowledge_items_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'knowledge_items_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'knowledge_items_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX knowledge_items_county_id_status_item_type_index ON public.knowledge_items USING btree (county_id, status, item_type);
CREATE INDEX knowledge_items_full_text_search_idx ON public.knowledge_items USING gin (to_tsvector('simple'::regconfig, (((((((((COALESCE(reference, ''::character varying))::text || ' '::text) || (COALESCE(title, ''::character varying))::text) || ' '::text) || COALESCE(summary, ''::text)) || ' '::text) || COALESCE(content_body, ''::text)) || ' '::text) || COALESCE((tags)::text, ''::text))));
CREATE INDEX knowledge_items_item_type_index ON public.knowledge_items USING btree (item_type);
CREATE INDEX knowledge_items_status_index ON public.knowledge_items USING btree (status);
CREATE INDEX knowledge_items_visibility_index ON public.knowledge_items USING btree (visibility);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_items');
    }
};
