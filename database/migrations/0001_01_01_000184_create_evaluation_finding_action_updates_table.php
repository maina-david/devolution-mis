<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_finding_action_updates', function (Blueprint $table): void {
            $table->uuid('id')->primary('evaluation_finding_action_updates_pkey');
            $table->uuid('evaluation_finding_action_id');
            $table->uuid('assessment_document_id');
            $table->uuid('submitted_by');
            $table->uuid('verified_by')->nullable();
            $table->decimal('progress_percentage', 5, 2);
            $table->text('narrative');
            $table->string('status', 255)->default('pending_verification');
            $table->text('decision_note')->nullable();
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('verified_at', 0)->nullable();
            $table->string('checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->foreign(['assessment_document_id'], 'evaluation_finding_action_updates_assessment_document_id_foreig')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['evaluation_finding_action_id'], 'evaluation_finding_action_updates_evaluation_finding_action_id_')
                ->references(['id'])
                ->on('evaluation_finding_actions')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'evaluation_finding_action_updates_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'evaluation_finding_action_updates_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."evaluation_finding_action_updates" ADD CONSTRAINT "evaluation_finding_action_updates_progress_check" CHECK (progress_percentage > 0::numeric AND progress_percentage <= 100::numeric);
CREATE INDEX evaluation_finding_action_updates_evaluation_finding_action_id_ ON public.evaluation_finding_action_updates USING btree (evaluation_finding_action_id, status);
CREATE TRIGGER evaluation_finding_action_updates_decided_immutable BEFORE DELETE OR UPDATE ON evaluation_finding_action_updates FOR EACH ROW EXECUTE FUNCTION protect_decided_evaluation_finding_action_updates();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_finding_action_updates');
    }
};
