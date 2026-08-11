<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_type', 255)->nullable();
            $table->bigInteger('owner_id')->nullable();
            $table->string('name', 255);
            $table->string('secret', 255)->nullable();
            $table->string('provider', 255)->nullable();
            $table->text('redirect_uris');
            $table->text('grant_types');
            $table->boolean('revoked');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX oauth_clients_owner_type_owner_id_index ON public.oauth_clients USING btree (owner_type, owner_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_clients');
    }
};
