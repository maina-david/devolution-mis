<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
            $table->char('id', 80);
            $table->char('access_token_id', 80);
            $table->boolean('revoked');
            $table->timestamp('expires_at', 0)->nullable();
            $table->primary(['id'], 'oauth_refresh_tokens_pkey');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX oauth_refresh_tokens_access_token_id_index ON public.oauth_refresh_tokens USING btree (access_token_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_refresh_tokens');
    }
};
