<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_documents_pkey');
            $table->uuid('assessment_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('category', 255);
            $table->string('title', 255);
            $table->string('path', 255);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->text('description')->nullable();
            $table->date('document_date')->nullable();
            $table->integer('version')->default(1);
            $table->json('tags')->nullable();
            $table->date('retention_until')->nullable();
            $table->string('verification_status', 255)->default('pending');
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->string('source_type', 20)->default('digital');
            $table->uuid('assessment_criterion_id')->nullable();
            $table->uuid('criterion_evidence_requirement_id')->nullable();
            $table->uuid('current_version_id')->nullable();
            $table->char('content_checksum', 64)->nullable();
            $table->string('scan_status', 255)->default('pending');
            $table->string('ocr_status', 255)->default('not_requested');
            $table->string('security_classification', 255)->default('official');
            $table->string('record_status', 255)->default('active');
            $table->foreign(['assessment_criterion_id'], 'assessment_documents_assessment_criterion_id_foreign')
                ->references(['id'])
                ->on('assessment_criteria')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_id'], 'assessment_documents_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'assessment_documents_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['criterion_evidence_requirement_id'], 'assessment_documents_criterion_evidence_requirement_id_foreign')
                ->references(['id'])
                ->on('criterion_evidence_requirements')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['uploaded_by'], 'assessment_documents_uploaded_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_documents_category_index ON public.assessment_documents USING btree (category);
CREATE INDEX assessment_documents_content_checksum_index ON public.assessment_documents USING btree (content_checksum);
CREATE INDEX assessment_documents_document_date_index ON public.assessment_documents USING btree (document_date);
CREATE INDEX assessment_documents_mime_type_index ON public.assessment_documents USING btree (mime_type);
CREATE INDEX assessment_documents_ocr_status_index ON public.assessment_documents USING btree (ocr_status);
CREATE INDEX assessment_documents_record_status_index ON public.assessment_documents USING btree (record_status);
CREATE INDEX assessment_documents_retention_until_index ON public.assessment_documents USING btree (retention_until);
CREATE INDEX assessment_documents_scan_status_index ON public.assessment_documents USING btree (scan_status);
CREATE INDEX assessment_documents_security_classification_index ON public.assessment_documents USING btree (security_classification);
CREATE INDEX assessment_documents_source_type_index ON public.assessment_documents USING btree (source_type);
CREATE INDEX assessment_documents_verification_status_index ON public.assessment_documents USING btree (verification_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_documents');
    }
};
