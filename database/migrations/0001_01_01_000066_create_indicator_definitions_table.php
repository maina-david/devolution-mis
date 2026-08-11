<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description');
            $table->uuid('sector_id')->nullable();
            $table->uuid('programme_id')->nullable();
            $table->string('results_level', 255);
            $table->string('unit_of_measure', 255);
            $table->string('value_type', 255)->default('number');
            $table->string('direction', 255)->default('increase');
            $table->string('frequency', 255);
            $table->jsonb('disaggregation_dimensions')->nullable();
            $table->jsonb('calculation_formula')->nullable();
            $table->text('data_source');
            $table->text('verification_method');
            $table->integer('version')->default(1);
            $table->string('status', 255)->default('draft');
            $table->timestamp('effective_from', 0)->nullable();
            $table->timestamp('effective_to', 0)->nullable();
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('supersedes_id')->nullable();
            $table->text('change_summary')->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code', 'version'], 'indicator_definitions_code_version_unique');
            $table->unique(['supersedes_id'], 'indicator_definitions_supersedes_id_unique');
            $table->foreign(['approved_by'], 'indicator_definitions_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'indicator_definitions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['programme_id'], 'indicator_definitions_programme_id_foreign')
                ->references(['id'])
                ->on('programmes')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'indicator_definitions_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'indicator_definitions_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX indicator_definitions_code_status_version_index ON public.indicator_definitions USING btree (code, status, version);
CREATE INDEX indicator_definitions_effective_from_index ON public.indicator_definitions USING btree (effective_from);
CREATE INDEX indicator_definitions_effective_to_index ON public.indicator_definitions USING btree (effective_to);
CREATE INDEX indicator_definitions_frequency_index ON public.indicator_definitions USING btree (frequency);
CREATE INDEX indicator_definitions_results_level_index ON public.indicator_definitions USING btree (results_level);
CREATE INDEX indicator_definitions_sector_id_status_index ON public.indicator_definitions USING btree (sector_id, status);
CREATE INDEX indicator_definitions_status_index ON public.indicator_definitions USING btree (status);
CREATE TRIGGER protect_approved_indicator_definitions_trigger BEFORE DELETE OR UPDATE ON indicator_definitions FOR EACH ROW EXECUTE FUNCTION protect_approved_indicator_definition();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_definitions');
    }
};
