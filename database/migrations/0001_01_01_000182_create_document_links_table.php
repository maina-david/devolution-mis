<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_links', function (Blueprint $table): void {
            $table->uuid('id')->primary('document_links_pkey');
            $table->uuid('assessment_document_id');
            $table->string('subject_type', 255);
            $table->uuid('subject_id');
            $table->string('purpose', 255);
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_document_id', 'subject_type', 'subject_id'], 'document_links_assessment_document_id_subject_type_subject_id_u');
            $table->foreign(['assessment_document_id'], 'document_links_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'document_links_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX document_links_purpose_index ON public.document_links USING btree (purpose);
CREATE INDEX document_links_subject_type_subject_id_index ON public.document_links USING btree (subject_type, subject_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};
