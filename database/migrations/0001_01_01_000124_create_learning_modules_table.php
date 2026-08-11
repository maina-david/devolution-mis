<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_modules', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_modules_pkey');
            $table->uuid('learning_course_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->smallInteger('sequence');
            $table->boolean('is_required')->default(true);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_course_id', 'sequence'], 'learning_modules_learning_course_id_sequence_unique');
            $table->foreign(['learning_course_id'], 'learning_modules_learning_course_id_foreign')
                ->references(['id'])
                ->on('learning_courses')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_modules');
    }
};
