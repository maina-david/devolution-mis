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
            $table->uuid('current_team_id')->nullable();
            $table->timestampTz('access_revoked_at', 0)->nullable();
            $table->uuid('access_revoked_by')->nullable();
            $table->text('access_revocation_reason')->nullable();
            $table->unique(['email'], 'users_email_unique');
            $table->foreign(['county_id'], 'users_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['current_team_id'], 'users_current_team_id_foreign')
                ->references(['id'])
                ->on('teams')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX users_access_revoked_at_index ON public.users USING btree (access_revoked_at);
CREATE INDEX users_county_id_index ON public.users USING btree (county_id);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
