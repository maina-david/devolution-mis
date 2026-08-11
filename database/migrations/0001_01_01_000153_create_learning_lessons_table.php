<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_lessons', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_lessons_pkey');
            $table->uuid('learning_module_id');
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->string('content_type', 255);
            $table->text('content_body')->nullable();
            $table->string('content_url', 255)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->string('content_checksum', 255)->nullable();
            $table->integer('estimated_minutes')->default(0);
            $table->smallInteger('sequence');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_downloadable')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_module_id', 'sequence'], 'learning_lessons_learning_module_id_sequence_unique');
            $table->foreign(['learning_module_id'], 'learning_lessons_learning_module_id_foreign')
                ->references(['id'])
                ->on('learning_modules')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_lessons_content_type_index ON public.learning_lessons USING btree (content_type);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_lessons');
    }
};
