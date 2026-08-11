<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolution_project_county', function (Blueprint $table): void {
            $table->uuid('devolution_project_id');
            $table->uuid('county_id');
            $table->boolean('is_lead')->default(false);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['devolution_project_id', 'county_id'], 'devolution_project_county_pkey');
            $table->foreign(['county_id'], 'devolution_project_county_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['devolution_project_id'], 'devolution_project_county_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX devolution_project_county_county_project_index ON public.devolution_project_county USING btree (county_id, devolution_project_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('devolution_project_county');
    }
};
