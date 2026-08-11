<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_dispositions', function (Blueprint $table): void {
            $table->uuid('id')->primary('document_dispositions_pkey');
            $table->uuid('assessment_document_id');
            $table->uuid('requested_by');
            $table->uuid('reviewed_by')->nullable();
            $table->uuid('executed_by')->nullable();
            $table->string('action', 40)->default('secure_destroy');
            $table->text('reason');
            $table->string('authority_reference', 255);
            $table->date('retention_due_at');
            $table->date('scheduled_for');
            $table->string('status', 30)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->timestampTz('execution_started_at', 0)->nullable();
            $table->timestampTz('executed_at', 0)->nullable();
            $table->jsonb('object_manifest')->nullable();
            $table->string('manifest_checksum', 64)->nullable();
            $table->integer('object_count')->default(0);
            $table->bigInteger('total_bytes')->default(DB::raw('\'0\'::bigint'));
            $table->text('execution_error')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->foreign(['assessment_document_id'], 'document_dispositions_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['executed_by'], 'document_dispositions_executed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requested_by'], 'document_dispositions_requested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'document_dispositions_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX document_dispositions_assessment_document_id_created_at_index ON public.document_dispositions USING btree (assessment_document_id, created_at);
CREATE UNIQUE INDEX document_dispositions_one_open_per_document ON public.document_dispositions USING btree (assessment_document_id) WHERE ((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('approved'::character varying)::text, ('executing'::character varying)::text, ('execution_failed'::character varying)::text]));
CREATE INDEX document_dispositions_status_scheduled_for_index ON public.document_dispositions USING btree (status, scheduled_for);
CREATE TRIGGER document_dispositions_executed_immutable BEFORE DELETE OR UPDATE ON document_dispositions FOR EACH ROW EXECUTE FUNCTION prevent_executed_document_disposition_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_dispositions');
    }
};
