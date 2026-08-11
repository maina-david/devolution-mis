<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_systems', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_organization_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->text('purpose');
            $table->string('system_owner', 255);
            $table->string('environment', 30)->default('sandbox');
            $table->string('transport', 30);
            $table->string('auth_scheme', 50);
            $table->string('credential_reference', 255)->nullable();
            $table->string('base_url', 2000)->nullable();
            $table->string('direction', 30);
            $table->string('data_classification', 50)->default('official');
            $table->string('status', 30)->default('design');
            $table->string('production_approval_reference', 255)->nullable();
            $table->timestampTz('production_approved_at', 0)->nullable();
            $table->string('health_status', 30)->default('unknown');
            $table->timestampTz('last_health_check_at', 0)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('registered_by')->nullable();
            $table->uuid('oauth_client_id')->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'integration_systems_code_unique');
            $table->unique(['oauth_client_id'], 'integration_systems_oauth_client_id_unique');
            $table->foreign(['oauth_client_id'], 'integration_systems_oauth_client_id_foreign')
                ->references(['id'])
                ->on('oauth_clients')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['owner_organization_id'], 'integration_systems_owner_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'integration_systems_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['registered_by'], 'integration_systems_registered_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX integration_systems_status_health_status_index ON public.integration_systems USING btree (status, health_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_systems');
    }
};
