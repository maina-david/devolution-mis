<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_counties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('county_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('slug');
            $table->string('classification', 32)->default('constituency');
            $table->string('source_authority');
            $table->string('source_reference');
            $table->char('source_checksum_sha256', 64);
            $table->jsonb('boundary_geojson')->nullable();
            $table->char('boundary_checksum_sha256', 64)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['county_id', 'code']);
            $table->unique(['county_id', 'slug']);
            $table->index(['county_id', 'name']);
        });

        DB::statement("ALTER TABLE sub_counties ADD CONSTRAINT sub_counties_source_checksum_check CHECK (source_checksum_sha256 ~ '^[a-f0-9]{64}$')");
        DB::statement("ALTER TABLE sub_counties ADD CONSTRAINT sub_counties_boundary_checksum_check CHECK (boundary_checksum_sha256 IS NULL OR boundary_checksum_sha256 ~ '^[a-f0-9]{64}$')");
        DB::statement('ALTER TABLE sub_counties ADD CONSTRAINT sub_counties_effective_period_check CHECK (effective_to IS NULL OR effective_to >= effective_from)');
        DB::statement("ALTER TABLE sub_counties ADD CONSTRAINT sub_counties_classification_check CHECK (classification IN ('county_sub_county', 'national_sub_county', 'constituency'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_counties');
    }
};
