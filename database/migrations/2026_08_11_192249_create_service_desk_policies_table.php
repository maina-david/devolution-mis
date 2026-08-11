<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_desk_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 80);
            $table->unsignedInteger('version');
            $table->string('name', 255);
            $table->text('description');
            $table->uuid('business_calendar_id');
            $table->string('authority_status', 20)->default('provisional');
            $table->string('approval_reference', 255)->nullable();
            $table->jsonb('categories');
            $table->jsonb('channels');
            $table->jsonb('priority_targets');
            $table->jsonb('escalation_rules');
            $table->timestampTz('effective_from', 0);
            $table->timestampTz('effective_to', 0)->nullable();
            $table->string('status', 20)->default('draft');
            $table->uuid('created_by');
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->unique(['code', 'version']);
            $table->foreign('business_calendar_id')->references('id')->on('business_calendars')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE service_desk_policies ADD CONSTRAINT service_desk_policies_status_check CHECK (status IN ('draft', 'published'));
ALTER TABLE service_desk_policies ADD CONSTRAINT service_desk_policies_authority_status_check CHECK (authority_status IN ('provisional', 'approved'));
ALTER TABLE service_desk_policies ADD CONSTRAINT service_desk_policies_approval_reference_check CHECK ((authority_status = 'approved') = (approval_reference IS NOT NULL));
ALTER TABLE service_desk_policies ADD CONSTRAINT service_desk_policies_effective_dates_check CHECK (effective_to IS NULL OR effective_to > effective_from);
ALTER TABLE service_desk_policies ADD CONSTRAINT service_desk_policies_publication_check CHECK ((status = 'published') = (published_by IS NOT NULL AND published_at IS NOT NULL AND checksum IS NOT NULL));
CREATE INDEX service_desk_policies_effective_index ON service_desk_policies (status, effective_from, effective_to);
CREATE OR REPLACE FUNCTION protect_published_service_desk_policy() RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'published' THEN
        RAISE EXCEPTION 'Published service-desk policies are immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER service_desk_policies_immutable BEFORE UPDATE OR DELETE ON service_desk_policies FOR EACH ROW EXECUTE FUNCTION protect_published_service_desk_policy();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_desk_policies');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_published_service_desk_policy()');
    }
};
