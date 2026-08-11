<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolution_innovations', function (Blueprint $table): void {
            $table->uuid('id')->primary('devolution_innovations_pkey');
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('sector_id')->nullable();
            $table->uuid('submitted_by');
            $table->uuid('reviewed_by')->nullable();
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->text('problem_statement');
            $table->text('proposed_solution');
            $table->text('expected_impact');
            $table->string('maturity_level', 255)->default('idea');
            $table->string('stage', 255)->default('identified');
            $table->string('status', 255)->default('draft');
            $table->text('incubation_support')->nullable();
            $table->string('evidence_reference', 255)->nullable();
            $table->timestamp('submitted_at', 0)->nullable();
            $table->timestamp('decision_due_at', 0)->nullable();
            $table->timestamp('decided_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['reference'], 'devolution_innovations_reference_unique');
            $table->foreign(['county_id'], 'devolution_innovations_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'devolution_innovations_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'devolution_innovations_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'devolution_innovations_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'devolution_innovations_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'devolution_innovations_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX devolution_innovations_county_id_stage_status_index ON public.devolution_innovations USING btree (county_id, stage, status);
CREATE INDEX devolution_innovations_maturity_level_index ON public.devolution_innovations USING btree (maturity_level);
CREATE INDEX devolution_innovations_stage_index ON public.devolution_innovations USING btree (stage);
CREATE INDEX devolution_innovations_status_index ON public.devolution_innovations USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('devolution_innovations');
    }
};
