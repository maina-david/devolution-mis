<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_extraction_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary('document_extraction_attempts_pkey');
            $table->uuid('document_extraction_id');
            $table->uuid('document_version_id');
            $table->smallInteger('attempt_number');
            $table->uuid('initiated_by')->nullable();
            $table->string('initiated_by_name', 255)->nullable();
            $table->string('trigger_source', 255);
            $table->string('status', 255)->default('processing');
            $table->string('engine', 255)->nullable();
            $table->string('language', 255);
            $table->char('text_checksum_sha256', 64)->nullable();
            $table->bigInteger('character_count')->default(DB::raw('\'0\'::bigint'));
            $table->integer('page_count')->nullable();
            $table->string('error_code', 255)->nullable();
            $table->text('error_detail')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0)->nullable();
            $table->bigInteger('duration_ms')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['document_extraction_id', 'attempt_number'], 'document_extraction_attempts_document_extraction_id_attempt_num');
            $table->foreign(['document_extraction_id'], 'document_extraction_attempts_document_extraction_id_foreign')
                ->references(['id'])
                ->on('document_extractions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['document_version_id'], 'document_extraction_attempts_document_version_id_foreign')
                ->references(['id'])
                ->on('document_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['initiated_by'], 'document_extraction_attempts_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX document_extraction_attempts_completed_at_index ON public.document_extraction_attempts USING btree (completed_at);
CREATE INDEX document_extraction_attempts_engine_index ON public.document_extraction_attempts USING btree (engine);
CREATE INDEX document_extraction_attempts_error_code_index ON public.document_extraction_attempts USING btree (error_code);
CREATE INDEX document_extraction_attempts_started_at_index ON public.document_extraction_attempts USING btree (started_at);
CREATE INDEX document_extraction_attempts_status_index ON public.document_extraction_attempts USING btree (status);
CREATE INDEX document_extraction_attempts_trigger_source_index ON public.document_extraction_attempts USING btree (trigger_source);
CREATE TRIGGER document_extraction_attempts_final_immutable BEFORE DELETE OR UPDATE ON document_extraction_attempts FOR EACH ROW EXECUTE FUNCTION protect_final_document_extraction_attempts();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_extraction_attempts');
    }
};
