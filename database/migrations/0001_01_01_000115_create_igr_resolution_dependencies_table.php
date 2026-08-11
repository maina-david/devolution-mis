<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_resolution_dependencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('dependent_resolution_id');
            $table->uuid('prerequisite_resolution_id');
            $table->string('dependency_type', 255)->default('blocks');
            $table->text('rationale');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['dependent_resolution_id', 'prerequisite_resolution_id'], 'igr_resolution_dependency_pair_unique');
            $table->foreign(['created_by'], 'igr_resolution_dependencies_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['dependent_resolution_id'], 'igr_resolution_dependencies_dependent_resolution_id_foreign')
                ->references(['id'])
                ->on('igr_resolutions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['prerequisite_resolution_id'], 'igr_resolution_dependencies_prerequisite_resolution_id_foreign')
                ->references(['id'])
                ->on('igr_resolutions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_resolution_dependencies_dependency_type_index ON public.igr_resolution_dependencies USING btree (dependency_type);
CREATE INDEX igr_resolution_dependency_reverse_index ON public.igr_resolution_dependencies USING btree (prerequisite_resolution_id, dependent_resolution_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_resolution_dependencies');
    }
};
