<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table): void {
            $table->foreign(['parent_sector_id'], 'sectors_parent_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreign(['access_revoked_by'], 'users_access_revoked_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });
        Schema::table('indicator_definitions', function (Blueprint $table): void {
            $table->foreign(['supersedes_id'], 'indicator_definitions_supersedes_id_foreign')
                ->references(['id'])
                ->on('indicator_definitions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
        Schema::table('assessment_corrective_updates', function (Blueprint $table): void {
            $table->foreign(['assessment_document_id'], 'assessment_corrective_updates_assessment_document_id_foreign')
                ->references(['id'])
                ->on('assessment_documents')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
        Schema::table('assessment_documents', function (Blueprint $table): void {
            $table->foreign(['current_version_id'], 'assessment_documents_current_version_id_foreign')
                ->references(['id'])
                ->on('document_versions')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

    }

    public function down(): void
    {
        // Dropping the parent table migrations removes dependent database objects.
    }
};
