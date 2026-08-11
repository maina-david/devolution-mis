<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_procurements', function (Blueprint $table): void {
            $table->uuid('id')->primary('project_procurements_pkey');
            $table->uuid('devolution_project_id');
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->string('method', 255);
            $table->string('status', 255)->default('planned');
            $table->decimal('estimated_value', 20, 2);
            $table->decimal('contract_value', 20, 2)->nullable();
            $table->char('currency', 3);
            $table->date('planned_notice_date')->nullable();
            $table->date('award_date')->nullable();
            $table->string('supplier_name', 255)->nullable();
            $table->text('contract_reference')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['reference'], 'project_procurements_reference_unique');
            $table->foreign(['devolution_project_id'], 'project_procurements_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX project_procurements_method_index ON public.project_procurements USING btree (method);
CREATE INDEX project_procurements_status_index ON public.project_procurements USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('project_procurements');
    }
};
