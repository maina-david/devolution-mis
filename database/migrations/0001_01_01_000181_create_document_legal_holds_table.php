<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_legal_holds', function (Blueprint $table): void {
            $table->uuid('id')->primary('document_legal_holds_pkey');
            $table->uuid('assessment_document_id');
            $table->string('reference', 255);
            $table->text('reason');
            $table->string('authority', 255);
            $table->uuid('placed_by');
            $table->timestampTz('placed_at', 0);
            $table->uuid('released_by')->nullable();
            $table->timestampTz('released_at', 0)->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['reference'], 'document_legal_holds_reference_unique');
            $table->foreign(['assessment_document_id'], 'document_legal_holds_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['placed_by'], 'document_legal_holds_placed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['released_by'], 'document_legal_holds_released_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX document_legal_holds_assessment_document_id_released_at_index ON public.document_legal_holds USING btree (assessment_document_id, released_at);
CREATE INDEX document_legal_holds_placed_at_index ON public.document_legal_holds USING btree (placed_at);
CREATE INDEX document_legal_holds_released_at_index ON public.document_legal_holds USING btree (released_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_legal_holds');
    }
};
