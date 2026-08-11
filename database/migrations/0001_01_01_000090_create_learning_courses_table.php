<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('sector_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('owner_id');
            $table->string('code', 255);
            $table->string('slug', 255);
            $table->string('title', 255);
            $table->text('summary');
            $table->text('description');
            $table->string('category', 255);
            $table->string('level', 255)->default('foundation');
            $table->string('delivery_mode', 255)->default('self_paced');
            $table->string('language', 255);
            $table->integer('estimated_minutes')->default(0);
            $table->decimal('passing_score', 5, 2)->default(DB::raw('\'70\'::numeric'));
            $table->smallInteger('maximum_attempts')->default(DB::raw('\'3\'::smallint'));
            $table->string('status', 255)->default('draft');
            $table->timestamp('published_at', 0)->nullable();
            $table->timestamp('retired_at', 0)->nullable();
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'learning_courses_code_unique');
            $table->unique(['slug'], 'learning_courses_slug_unique');
            $table->foreign(['county_id'], 'learning_courses_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'learning_courses_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['owner_id'], 'learning_courses_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'learning_courses_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'learning_courses_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'learning_courses_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_courses_category_index ON public.learning_courses USING btree (category);
CREATE INDEX learning_courses_delivery_mode_index ON public.learning_courses USING btree (delivery_mode);
CREATE INDEX learning_courses_level_index ON public.learning_courses USING btree (level);
CREATE INDEX learning_courses_status_index ON public.learning_courses USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_courses');
    }
};
