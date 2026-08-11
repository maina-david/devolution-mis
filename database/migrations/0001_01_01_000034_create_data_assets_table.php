<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('data_owner_id')->nullable();
            $table->uuid('steward_id')->nullable();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description');
            $table->string('module', 255);
            $table->string('authoritative_source', 255);
            $table->string('classification', 30);
            $table->boolean('contains_personal_data')->default(false);
            $table->boolean('contains_sensitive_personal_data')->default(false);
            $table->jsonb('personal_data_categories')->nullable();
            $table->jsonb('data_subject_categories')->nullable();
            $table->jsonb('storage_locations');
            $table->string('residency_country', 2);
            $table->string('quality_standard', 255)->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['code'], 'data_assets_code_unique');
            $table->foreign(['data_owner_id'], 'data_assets_data_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['steward_id'], 'data_assets_steward_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX data_assets_classification_status_index ON public.data_assets USING btree (classification, status);
CREATE INDEX data_assets_module_contains_personal_data_index ON public.data_assets USING btree (module, contains_personal_data);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_assets');
    }
};
