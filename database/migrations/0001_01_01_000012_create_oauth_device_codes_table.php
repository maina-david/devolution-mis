<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_device_codes', function (Blueprint $table): void {
            $table->char('id', 80);
            $table->bigInteger('user_id')->nullable();
            $table->uuid('client_id');
            $table->char('user_code', 8);
            $table->text('scopes');
            $table->boolean('revoked');
            $table->timestamp('user_approved_at', 0)->nullable();
            $table->timestamp('last_polled_at', 0)->nullable();
            $table->timestamp('expires_at', 0)->nullable();
            $table->primary(['id'], 'oauth_device_codes_pkey');
            $table->unique(['user_code'], 'oauth_device_codes_user_code_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX oauth_device_codes_client_id_index ON public.oauth_device_codes USING btree (client_id);
CREATE INDEX oauth_device_codes_user_id_index ON public.oauth_device_codes USING btree (user_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_device_codes');
    }
};
