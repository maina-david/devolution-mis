<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key', 255);
            $table->string('owner', 255);
            $table->bigInteger('expiration');
            $table->primary(['key'], 'cache_locks_pkey');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
    }
};
