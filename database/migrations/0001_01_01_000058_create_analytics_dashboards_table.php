<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_dashboards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('created_by');
            $table->uuid('published_by')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description');
            $table->jsonb('audience_roles');
            $table->string('status', 255)->default('draft');
            $table->string('checksum', 64)->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'analytics_dashboards_code_unique');
            $table->foreign(['county_id'], 'analytics_dashboards_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'analytics_dashboards_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['published_by'], 'analytics_dashboards_published_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'analytics_dashboards_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX analytics_dashboards_status_index ON public.analytics_dashboards USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_dashboards');
    }
};
