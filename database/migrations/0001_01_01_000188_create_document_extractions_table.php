<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extractions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('document_version_id');
            $table->string('status', 255)->default('pending');
            $table->string('engine', 255)->nullable();
            $table->string('language', 255);
            $table->text('extracted_text')->nullable();
            $table->char('text_checksum_sha256', 64)->nullable();
            $table->bigInteger('character_count')->default(DB::raw('\'0\'::bigint'));
            $table->integer('page_count')->nullable();
            $table->smallInteger('attempt_count')->default(DB::raw('\'0\'::smallint'));
            $table->string('error_code', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('started_at', 0)->nullable();
            $table->timestampTz('completed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['document_version_id'], 'document_extractions_document_version_id_unique');
            $table->foreign(['document_version_id'], 'document_extractions_document_version_id_foreign')
                ->references(['id'])
                ->on('document_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX document_extractions_completed_at_index ON public.document_extractions USING btree (completed_at);
CREATE INDEX document_extractions_engine_index ON public.document_extractions USING btree (engine);
CREATE INDEX document_extractions_error_code_index ON public.document_extractions USING btree (error_code);
CREATE INDEX document_extractions_started_at_index ON public.document_extractions USING btree (started_at);
CREATE INDEX document_extractions_status_index ON public.document_extractions USING btree (status);
CREATE INDEX document_extractions_text_checksum_sha256_index ON public.document_extractions USING btree (text_checksum_sha256);
CREATE INDEX document_extractions_text_search_idx ON public.document_extractions USING gin (to_tsvector('simple'::regconfig, COALESCE(extracted_text, ''::text)));
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extractions');
    }
};
