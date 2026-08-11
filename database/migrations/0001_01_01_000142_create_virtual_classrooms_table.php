<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_classrooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('learning_course_id');
            $table->uuid('facilitator_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('starts_at', 0);
            $table->timestamp('ends_at', 0);
            $table->string('platform', 255);
            $table->string('join_url', 255);
            $table->string('recording_url', 255)->nullable();
            $table->integer('capacity')->nullable();
            $table->string('status', 255)->default('scheduled');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['created_by'], 'virtual_classrooms_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['facilitator_id'], 'virtual_classrooms_facilitator_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_course_id'], 'virtual_classrooms_learning_course_id_foreign')
                ->references(['id'])
                ->on('learning_courses')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX virtual_classrooms_starts_at_index ON public.virtual_classrooms USING btree (starts_at);
CREATE INDEX virtual_classrooms_status_index ON public.virtual_classrooms USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_classrooms');
    }
};
