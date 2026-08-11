<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_gap_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary('igr_gap_categories_pkey');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description');
            $table->string('default_severity', 255)->default('medium');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'igr_gap_categories_code_unique');
            $table->foreign(['created_by'], 'igr_gap_categories_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_gap_categories_is_active_index ON public.igr_gap_categories USING btree (is_active);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_gap_categories');
    }
};
