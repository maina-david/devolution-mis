<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passkeys', function (Blueprint $table): void {
            $table->uuid('id')->primary('passkeys_pkey');
            $table->uuid('user_id');
            $table->string('name', 255);
            $table->string('credential_id', 255);
            $table->json('credential');
            $table->timestamp('last_used_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['credential_id'], 'passkeys_credential_id_unique');
            $table->foreign(['user_id'], 'passkeys_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX passkeys_user_id_index ON public.passkeys USING btree (user_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};
