<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_case_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary('citizen_case_attachments_pkey');
            $table->uuid('citizen_case_id');
            $table->uuid('citizen_case_message_id')->nullable();
            $table->string('title', 255);
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime_type', 255);
            $table->bigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('source_type', 255);
            $table->string('scan_status', 255);
            $table->jsonb('scan_details')->nullable();
            $table->string('ocr_status', 255)->default('not_required');
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['citizen_case_id'], 'citizen_case_attachments_citizen_case_id_foreign')
                ->references(['id'])
                ->on('citizen_cases')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['citizen_case_message_id'], 'citizen_case_attachments_citizen_case_message_id_foreign')
                ->references(['id'])
                ->on('citizen_case_messages')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['uploaded_by'], 'citizen_case_attachments_uploaded_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX citizen_case_attachments_citizen_case_id_scan_status_index ON public.citizen_case_attachments USING btree (citizen_case_id, scan_status);
CREATE INDEX citizen_case_attachments_scan_status_index ON public.citizen_case_attachments USING btree (scan_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_case_attachments');
    }
};
