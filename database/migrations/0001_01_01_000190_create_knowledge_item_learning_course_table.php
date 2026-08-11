<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_item_learning_course', function (Blueprint $table): void {
            $table->uuid('knowledge_item_id');
            $table->uuid('learning_course_id');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['knowledge_item_id', 'learning_course_id'], 'knowledge_item_learning_course_pkey');
            $table->foreign(['knowledge_item_id'], 'knowledge_item_learning_course_knowledge_item_id_foreign')
                ->references(['id'])
                ->on('knowledge_items')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['learning_course_id'], 'knowledge_item_learning_course_learning_course_id_foreign')
                ->references(['id'])
                ->on('learning_courses')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_item_learning_course');
    }
};
