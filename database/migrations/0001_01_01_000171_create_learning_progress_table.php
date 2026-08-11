<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('learning_enrollment_id');
            $table->uuid('learning_lesson_id');
            $table->string('status', 255)->default('not_started');
            $table->decimal('progress_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->integer('time_spent_seconds')->default(0);
            $table->timestamp('started_at', 0)->nullable();
            $table->timestamp('completed_at', 0)->nullable();
            $table->timestamp('last_position_at', 0)->nullable();
            $table->jsonb('state')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_enrollment_id', 'learning_lesson_id'], 'learning_progress_learning_enrollment_id_learning_lesson_id_uni');
            $table->foreign(['learning_enrollment_id'], 'learning_progress_learning_enrollment_id_foreign')
                ->references(['id'])
                ->on('learning_enrollments')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['learning_lesson_id'], 'learning_progress_learning_lesson_id_foreign')
                ->references(['id'])
                ->on('learning_lessons')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_progress_status_index ON public.learning_progress USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_progress');
    }
};
