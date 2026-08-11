<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key', 255);
            $table->text('value');
            $table->bigInteger('expiration');
            $table->primary(['key'], 'cache_pkey');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
    }
};
