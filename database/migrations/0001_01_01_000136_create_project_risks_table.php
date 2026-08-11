<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_risks', function (Blueprint $table): void {
            $table->uuid('id')->primary('project_risks_pkey');
            $table->uuid('devolution_project_id');
            $table->string('code', 255);
            $table->string('category', 255);
            $table->text('description');
            $table->smallInteger('probability');
            $table->smallInteger('impact');
            $table->smallInteger('residual_probability')->nullable();
            $table->smallInteger('residual_impact')->nullable();
            $table->text('mitigation');
            $table->string('status', 255)->default('open');
            $table->uuid('owner_id')->nullable();
            $table->date('review_due_date')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['devolution_project_id', 'code'], 'project_risks_devolution_project_id_code_unique');
            $table->foreign(['devolution_project_id'], 'project_risks_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['owner_id'], 'project_risks_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX project_risks_category_index ON public.project_risks USING btree (category);
CREATE INDEX project_risks_review_due_date_index ON public.project_risks USING btree (review_due_date);
CREATE INDEX project_risks_status_index ON public.project_risks USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_risks');
    }
};
