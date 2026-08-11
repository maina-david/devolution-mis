<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('training_participant_id');
            $table->uuid('assessed_by');
            $table->string('assessment_type', 255);
            $table->decimal('score', 5, 2);
            $table->string('outcome', 255);
            $table->text('feedback');
            $table->jsonb('evidence_references');
            $table->timestampTz('assessed_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['training_participant_id', 'assessment_type'], 'training_assessments_training_participant_id_assessment_type_un');
            $table->foreign(['assessed_by'], 'training_assessments_assessed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['training_participant_id'], 'training_assessments_training_participant_id_foreign')
                ->references(['id'])
                ->on('training_participants')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX training_assessments_assessment_type_index ON public.training_assessments USING btree (assessment_type);
CREATE INDEX training_assessments_outcome_index ON public.training_assessments USING btree (outcome);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('training_assessments');
    }
};
