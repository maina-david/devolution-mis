<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('learning_course_id');
            $table->uuid('user_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->string('status', 255)->default('enrolled');
            $table->decimal('progress_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('best_score', 5, 2)->nullable();
            $table->timestamp('enrolled_at', 0);
            $table->timestamp('started_at', 0)->nullable();
            $table->timestamp('last_activity_at', 0)->nullable();
            $table->timestamp('completed_at', 0)->nullable();
            $table->uuid('enrolled_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_course_id', 'user_id'], 'learning_enrollments_learning_course_id_user_id_unique');
            $table->foreign(['county_id'], 'learning_enrollments_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['enrolled_by'], 'learning_enrollments_enrolled_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_course_id'], 'learning_enrollments_learning_course_id_foreign')
                ->references(['id'])
                ->on('learning_courses')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['organization_id'], 'learning_enrollments_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'learning_enrollments_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_enrollments_county_id_status_index ON public.learning_enrollments USING btree (county_id, status);
CREATE INDEX learning_enrollments_status_index ON public.learning_enrollments USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_enrollments');
    }
};
