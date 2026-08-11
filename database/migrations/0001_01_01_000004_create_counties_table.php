<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->smallInteger('code');
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->string('region', 255)->nullable();
            $table->decimal('map_x', 5, 2);
            $table->decimal('map_y', 5, 2);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->string('logo_path', 255)->nullable();
            $table->text('logo_source_url')->nullable();
            $table->text('official_website_url')->nullable();
            $table->string('logo_source_authority', 255)->nullable();
            $table->string('logo_source_kind', 255)->nullable();
            $table->char('logo_checksum_sha256', 64)->nullable();
            $table->date('logo_verified_at')->nullable();
            $table->char('logo_source_checksum_sha256', 64)->nullable();
            $table->unique(['code'], 'counties_code_unique');
            $table->unique(['name'], 'counties_name_unique');
            $table->unique(['slug'], 'counties_slug_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX counties_region_index ON public.counties USING btree (region);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('counties');
    }
};
