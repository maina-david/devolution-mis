<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary('workflow_definitions_pkey');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->string('module', 255);
            $table->text('description')->nullable();
            $table->string('status', 255)->default('active');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'workflow_definitions_code_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX workflow_definitions_module_index ON public.workflow_definitions USING btree (module);
CREATE INDEX workflow_definitions_name_index ON public.workflow_definitions USING btree (name);
CREATE INDEX workflow_definitions_status_index ON public.workflow_definitions USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_definitions');
    }
};
