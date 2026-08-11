<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_budget_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary('project_budget_lines_pkey');
            $table->uuid('devolution_project_id');
            $table->string('code', 255);
            $table->string('category', 255);
            $table->string('description', 255);
            $table->decimal('approved_amount', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('committed_amount', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('actual_amount', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->char('currency', 3);
            $table->string('financial_year', 255);
            $table->string('funding_source', 255)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['devolution_project_id', 'code', 'financial_year'], 'project_budget_lines_devolution_project_id_code_financial_year_');
            $table->foreign(['devolution_project_id'], 'project_budget_lines_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX project_budget_lines_category_index ON public.project_budget_lines USING btree (category);
CREATE INDEX project_budget_lines_financial_year_index ON public.project_budget_lines USING btree (financial_year);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_budget_lines');
    }
};
