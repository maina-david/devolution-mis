<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_definition_id');
            $table->integer('version');
            $table->string('status', 255)->default('draft');
            $table->jsonb('configuration');
            $table->char('checksum', 64)->nullable();
            $table->timestampTz('effective_from', 0)->nullable();
            $table->timestampTz('effective_to', 0)->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['workflow_definition_id', 'version'], 'workflow_versions_workflow_definition_id_version_unique');
            $table->foreign(['published_by'], 'workflow_versions_published_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_definition_id'], 'workflow_versions_workflow_definition_id_foreign')
                ->references(['id'])
                ->on('workflow_definitions')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX workflow_versions_checksum_index ON public.workflow_versions USING btree (checksum);
CREATE INDEX workflow_versions_effective_from_index ON public.workflow_versions USING btree (effective_from);
CREATE INDEX workflow_versions_effective_to_index ON public.workflow_versions USING btree (effective_to);
CREATE INDEX workflow_versions_status_index ON public.workflow_versions USING btree (status);
CREATE TRIGGER protect_released_workflow_versions_trigger BEFORE DELETE OR UPDATE ON workflow_versions FOR EACH ROW EXECUTE FUNCTION protect_released_workflow_versions();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
