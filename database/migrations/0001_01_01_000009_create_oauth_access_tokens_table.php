<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_access_tokens', function (Blueprint $table): void {
            $table->char('id', 80);
            $table->bigInteger('user_id')->nullable();
            $table->uuid('client_id');
            $table->string('name', 255)->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->timestamp('expires_at', 0)->nullable();
            $table->primary(['id'], 'oauth_access_tokens_pkey');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX oauth_access_tokens_user_id_index ON public.oauth_access_tokens USING btree (user_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_access_tokens');
    }
};
