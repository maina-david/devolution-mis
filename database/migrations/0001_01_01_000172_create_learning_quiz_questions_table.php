<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_quiz_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('learning_lesson_id');
            $table->text('question');
            $table->jsonb('options');
            $table->string('correct_option', 255);
            $table->text('explanation')->nullable();
            $table->decimal('points', 8, 2)->default(DB::raw('\'1\'::numeric'));
            $table->smallInteger('sequence');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_lesson_id', 'sequence'], 'learning_quiz_questions_learning_lesson_id_sequence_unique');
            $table->foreign(['learning_lesson_id'], 'learning_quiz_questions_learning_lesson_id_foreign')
                ->references(['id'])
                ->on('learning_lessons')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_quiz_questions');
    }
};
