<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devolution_project_indicator', function (Blueprint $table): void {
            $table->uuid('devolution_project_id');
            $table->uuid('indicator_definition_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->primary(['devolution_project_id', 'indicator_definition_id'], 'devolution_project_indicator_pkey');
            $table->foreign(['devolution_project_id'], 'devolution_project_indicator_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['indicator_definition_id'], 'devolution_project_indicator_indicator_definition_id_foreign')
                ->references(['id'])
                ->on('indicator_definitions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolution_project_indicator');
    }
};
