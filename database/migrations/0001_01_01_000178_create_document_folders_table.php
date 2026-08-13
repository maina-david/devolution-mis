<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('name', 120);
            $table->uuid('created_by');
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('county_id')->references('id')->on('counties')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('document_folders', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('document_folders')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE document_folders
    ADD CONSTRAINT document_folders_name_not_blank CHECK (btrim(name) <> '');
CREATE INDEX document_folders_parent_id_index ON document_folders (parent_id);
CREATE INDEX document_folders_county_id_index ON document_folders (county_id);
CREATE INDEX document_folders_name_search_index ON document_folders (lower(name));
CREATE UNIQUE INDEX document_folders_active_sibling_name_unique
    ON document_folders (parent_id, lower(name))
    WHERE parent_id IS NOT NULL AND deleted_at IS NULL;
CREATE UNIQUE INDEX document_folders_active_county_root_name_unique
    ON document_folders (county_id, lower(name))
    WHERE parent_id IS NULL AND county_id IS NOT NULL AND deleted_at IS NULL;
CREATE UNIQUE INDEX document_folders_active_national_root_name_unique
    ON document_folders (lower(name))
    WHERE parent_id IS NULL AND county_id IS NULL AND deleted_at IS NULL;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_folders');
    }
};
