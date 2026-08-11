<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_corrective_updates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assessment_corrective_action_id');
            $table->uuid('assessment_document_id');
            $table->uuid('submitted_by');
            $table->uuid('verified_by')->nullable();
            $table->decimal('progress_percentage', 5, 2);
            $table->text('narrative');
            $table->string('status', 30)->default('pending_verification');
            $table->text('decision_note')->nullable();
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('verified_at', 0)->nullable();
            $table->string('checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->foreign(['assessment_corrective_action_id'], 'assessment_corrective_updates_assessment_corrective_action_id_f')
                ->references(['id'])
                ->on('assessment_corrective_actions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'assessment_corrective_updates_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'assessment_corrective_updates_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_corrective_updates_assessment_corrective_action_id_s ON public.assessment_corrective_updates USING btree (assessment_corrective_action_id, status, submitted_at);
CREATE TRIGGER assessment_corrective_updates_decided_immutable BEFORE DELETE OR UPDATE ON assessment_corrective_updates FOR EACH ROW EXECUTE FUNCTION prevent_decided_corrective_update_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_corrective_updates');
    }
};
