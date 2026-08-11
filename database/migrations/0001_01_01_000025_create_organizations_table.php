<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->string('type', 255);
            $table->uuid('county_id')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('status', 255)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'organizations_code_unique');
            $table->foreign(['county_id'], 'organizations_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX organizations_name_index ON public.organizations USING btree (name);
CREATE INDEX organizations_status_index ON public.organizations USING btree (status);
CREATE INDEX organizations_type_index ON public.organizations USING btree (type);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
