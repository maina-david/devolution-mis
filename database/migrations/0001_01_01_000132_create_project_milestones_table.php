<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('devolution_project_id');
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('weight', 5, 2);
            $table->decimal('progress', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('status', 255)->default('not_started');
            $table->uuid('owner_id')->nullable();
            $table->jsonb('dependencies')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['devolution_project_id', 'code'], 'project_milestones_devolution_project_id_code_unique');
            $table->foreign(['devolution_project_id'], 'project_milestones_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['owner_id'], 'project_milestones_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX project_milestones_status_index ON public.project_milestones USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
