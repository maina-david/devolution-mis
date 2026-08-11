<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary('document_versions_pkey');
            $table->uuid('assessment_document_id');
            $table->integer('version_number');
            $table->string('storage_disk', 255)->default('local');
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 255);
            $table->bigInteger('size_bytes');
            $table->char('content_checksum', 64);
            $table->string('scan_status', 255)->default('pending');
            $table->jsonb('scan_details')->nullable();
            $table->timestampTz('scanned_at', 0)->nullable();
            $table->string('ocr_status', 255)->default('not_requested');
            $table->text('ocr_text')->nullable();
            $table->string('ocr_language', 255)->nullable();
            $table->jsonb('extraction_metadata')->nullable();
            $table->text('change_summary');
            $table->uuid('uploaded_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['assessment_document_id', 'version_number'], 'document_versions_assessment_document_id_version_number_unique');
            $table->foreign(['assessment_document_id'], 'document_versions_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['uploaded_by'], 'document_versions_uploaded_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX document_versions_content_checksum_index ON public.document_versions USING btree (content_checksum);
CREATE INDEX document_versions_mime_type_index ON public.document_versions USING btree (mime_type);
CREATE INDEX document_versions_ocr_status_index ON public.document_versions USING btree (ocr_status);
CREATE INDEX document_versions_scan_status_index ON public.document_versions USING btree (scan_status);
CREATE TRIGGER protect_document_version_history_trigger BEFORE DELETE OR UPDATE ON document_versions FOR EACH ROW EXECUTE FUNCTION protect_document_version_history();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
