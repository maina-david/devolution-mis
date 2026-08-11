<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_assessment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_assessment_attempts_pkey');
            $table->uuid('learning_enrollment_id');
            $table->smallInteger('attempt_number');
            $table->jsonb('answers');
            $table->jsonb('result_snapshot');
            $table->decimal('score', 5, 2);
            $table->boolean('passed');
            $table->timestamp('submitted_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_enrollment_id', 'attempt_number'], 'learning_assessment_attempts_learning_enrollment_id_attempt_num');
            $table->foreign(['learning_enrollment_id'], 'learning_assessment_attempts_learning_enrollment_id_foreign')
                ->references(['id'])
                ->on('learning_enrollments')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_assessment_attempts_passed_index ON public.learning_assessment_attempts USING btree (passed);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_assessment_attempts');
    }
};
