<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary('platform_settings_pkey');
            $table->string('key', 255);
            $table->string('group', 255);
            $table->string('label', 255);
            $table->text('value')->nullable();
            $table->string('type', 255)->default('text');
            $table->text('description')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['key'], 'platform_settings_key_unique');
            $table->foreign(['updated_by'], 'platform_settings_updated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX platform_settings_group_index ON public.platform_settings USING btree ("group");
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
