<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('county_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('county_id');
            $table->string('programme', 255);
            $table->string('financial_year', 255);
            $table->decimal('allocated_amount', 16, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('disbursed_amount', 16, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('status', 255)->default('planned');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['county_id', 'programme', 'financial_year'], 'county_grants_county_id_programme_financial_year_unique');
            $table->foreign(['county_id'], 'county_grants_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX county_grants_financial_year_index ON public.county_grants USING btree (financial_year);
CREATE INDEX county_grants_programme_index ON public.county_grants USING btree (programme);
CREATE INDEX county_grants_status_index ON public.county_grants USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('county_grants');
    }
};
