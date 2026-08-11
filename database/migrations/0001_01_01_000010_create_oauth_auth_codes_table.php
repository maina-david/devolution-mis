<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_auth_codes', function (Blueprint $table): void {
            $table->char('id', 80);
            $table->bigInteger('user_id');
            $table->uuid('client_id');
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamp('expires_at', 0)->nullable();
            $table->primary(['id'], 'oauth_auth_codes_pkey');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX oauth_auth_codes_user_id_index ON public.oauth_auth_codes USING btree (user_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_auth_codes');
    }
};
