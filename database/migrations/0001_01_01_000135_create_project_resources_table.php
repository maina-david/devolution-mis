<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_resources', function (Blueprint $table): void {
            $table->uuid('id')->primary('project_resources_pkey');
            $table->uuid('devolution_project_id');
            $table->string('code', 100);
            $table->string('name', 255);
            $table->string('resource_type', 30);
            $table->string('capacity_unit', 30);
            $table->decimal('capacity_per_day', 18, 4);
            $table->decimal('cost_rate', 18, 2);
            $table->char('currency', 3);
            $table->date('available_from');
            $table->date('available_to');
            $table->string('status', 20)->default('active');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['devolution_project_id', 'code'], 'project_resources_devolution_project_id_code_unique');
            $table->foreign(['created_by'], 'project_resources_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['devolution_project_id'], 'project_resources_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."project_resources" ADD CONSTRAINT "project_resources_availability_valid" CHECK (available_to >= available_from);
ALTER TABLE public."project_resources" ADD CONSTRAINT "project_resources_capacity_positive" CHECK (capacity_per_day > 0::numeric);
ALTER TABLE public."project_resources" ADD CONSTRAINT "project_resources_cost_nonnegative" CHECK (cost_rate >= 0::numeric);
CREATE INDEX project_resources_register_index ON public.project_resources USING btree (devolution_project_id, status, resource_type);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_resources');
    }
};
