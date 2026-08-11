<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('partner_type', 255);
            $table->string('country', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('focal_point_name', 255);
            $table->string('focal_point_email', 255);
            $table->string('focal_point_phone', 255)->nullable();
            $table->text('strategic_priorities')->nullable();
            $table->jsonb('modalities')->nullable();
            $table->string('status', 255)->default('active');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['organization_id'], 'partner_profiles_organization_id_unique');
            $table->foreign(['created_by'], 'partner_profiles_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['organization_id'], 'partner_profiles_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'partner_profiles_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_profiles_country_index ON public.partner_profiles USING btree (country);
CREATE INDEX partner_profiles_partner_type_index ON public.partner_profiles USING btree (partner_type);
CREATE INDEX partner_profiles_status_index ON public.partner_profiles USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_profiles');
    }
};
