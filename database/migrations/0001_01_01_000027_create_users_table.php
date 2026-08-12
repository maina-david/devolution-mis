<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->timestamp('email_verified_at', 0)->nullable();
            $table->string('password', 255);
            $table->uuid('county_id')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at', 0)->nullable();
            $table->timestampTz('access_revoked_at', 0)->nullable();
            $table->uuid('access_revoked_by')->nullable();
            $table->text('access_revocation_reason')->nullable();
            $table->string('profile_photo_disk', 32)->nullable();
            $table->text('profile_photo_path')->nullable();
            $table->string('profile_photo_mime_type', 100)->nullable();
            $table->unsignedBigInteger('profile_photo_size_bytes')->nullable();
            $table->string('profile_photo_checksum', 64)->nullable();
            $table->timestampTz('profile_photo_updated_at', 0)->nullable();
            $table->unique(['email'], 'users_email_unique');
            $table->foreign(['county_id'], 'users_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX users_access_revoked_at_index ON public.users USING btree (access_revoked_at);
CREATE INDEX users_county_id_index ON public.users USING btree (county_id);
ALTER TABLE public.users ADD CONSTRAINT users_profile_photo_complete_check CHECK (((profile_photo_path IS NULL) AND (profile_photo_disk IS NULL) AND (profile_photo_mime_type IS NULL) AND (profile_photo_size_bytes IS NULL) AND (profile_photo_checksum IS NULL) AND (profile_photo_updated_at IS NULL)) OR ((profile_photo_path IS NOT NULL) AND (profile_photo_disk IS NOT NULL) AND (profile_photo_mime_type = 'image/webp') AND (profile_photo_size_bytes > 0) AND (profile_photo_checksum ~ '^[0-9a-f]{64}$') AND (profile_photo_updated_at IS NOT NULL)));
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
