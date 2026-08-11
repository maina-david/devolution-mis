<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolution_projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description');
            $table->uuid('programme_id')->nullable();
            $table->uuid('sector_id');
            $table->uuid('lead_county_id');
            $table->uuid('funding_organization_id')->nullable();
            $table->uuid('project_manager_id')->nullable();
            $table->uuid('workflow_instance_id')->nullable();
            $table->string('lifecycle_stage', 255)->default('initiation');
            $table->string('status', 255)->default('draft');
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->decimal('approved_budget', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('committed_amount', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('actual_expenditure', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->char('currency', 3);
            $table->decimal('physical_progress', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('investment_registry_reference', 255)->nullable();
            $table->string('funding_source', 255)->nullable();
            $table->jsonb('location')->nullable();
            $table->jsonb('climate_risk_screening')->nullable();
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'devolution_projects_code_unique');
            $table->foreign(['created_by'], 'devolution_projects_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['funding_organization_id'], 'devolution_projects_funding_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['lead_county_id'], 'devolution_projects_lead_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['programme_id'], 'devolution_projects_programme_id_foreign')
                ->references(['id'])
                ->on('programmes')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['project_manager_id'], 'devolution_projects_project_manager_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'devolution_projects_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'devolution_projects_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'devolution_projects_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX devolution_projects_code_trgm_index ON public.devolution_projects USING gin (code gin_trgm_ops);
CREATE INDEX devolution_projects_investment_registry_reference_index ON public.devolution_projects USING btree (investment_registry_reference);
CREATE INDEX devolution_projects_lead_county_id_sector_id_status_index ON public.devolution_projects USING btree (lead_county_id, sector_id, status);
CREATE INDEX devolution_projects_lifecycle_stage_index ON public.devolution_projects USING btree (lifecycle_stage);
CREATE INDEX devolution_projects_status_index ON public.devolution_projects USING btree (status);
CREATE INDEX devolution_projects_title_trgm_index ON public.devolution_projects USING gin (title gin_trgm_ops);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('devolution_projects');
    }
};
