<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table): void {
            $table->uuid('id')->primary('sectors_pkey');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('parent_sector_id')->nullable();
            $table->unique(['code'], 'sectors_code_unique');
            $table->unique(['name'], 'sectors_name_unique');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."sectors" ADD CONSTRAINT "sectors_parent_not_self" CHECK (parent_sector_id IS NULL OR parent_sector_id <> id);
CREATE INDEX sectors_is_active_index ON public.sectors USING btree (is_active);
CREATE INDEX sectors_parent_active_index ON public.sectors USING btree (parent_sector_id, is_active);
CREATE TRIGGER sectors_prevent_hierarchy_cycle BEFORE INSERT OR UPDATE OF parent_sector_id ON sectors FOR EACH ROW EXECUTE FUNCTION prevent_sector_hierarchy_cycle();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
