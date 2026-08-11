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
        Schema::create('service_desk_roster_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('service_desk_policy_id');
            $table->uuid('user_id');
            $table->uuid('county_id')->nullable();
            $table->unsignedSmallInteger('tier');
            $table->string('duty_role', 20);
            $table->boolean('is_primary')->default(false);
            $table->timestampTz('starts_at', 0);
            $table->timestampTz('ends_at', 0)->nullable();
            $table->uuid('created_by');
            $table->timestampsTz(0);
            $table->softDeletesTz('deleted_at', 0);

            $table->foreign('service_desk_policy_id')->references('id')->on('service_desk_policies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('county_id')->references('id')->on('counties')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE service_desk_roster_members ADD CONSTRAINT service_desk_roster_members_tier_check CHECK (tier BETWEEN 1 AND 3);
ALTER TABLE service_desk_roster_members ADD CONSTRAINT service_desk_roster_members_role_check CHECK (duty_role IN ('responder', 'specialist', 'manager'));
ALTER TABLE service_desk_roster_members ADD CONSTRAINT service_desk_roster_members_dates_check CHECK (ends_at IS NULL OR ends_at > starts_at);
CREATE UNIQUE INDEX service_desk_roster_national_unique ON service_desk_roster_members (service_desk_policy_id, user_id) WHERE county_id IS NULL AND deleted_at IS NULL;
CREATE UNIQUE INDEX service_desk_roster_county_unique ON service_desk_roster_members (service_desk_policy_id, user_id, county_id) WHERE county_id IS NOT NULL AND deleted_at IS NULL;
CREATE INDEX service_desk_roster_active_index ON service_desk_roster_members (service_desk_policy_id, county_id, starts_at, ends_at);
CREATE OR REPLACE FUNCTION protect_published_service_desk_roster() RETURNS trigger AS $$
DECLARE
    governed_policy_id uuid;
BEGIN
    governed_policy_id := COALESCE(NEW.service_desk_policy_id, OLD.service_desk_policy_id);
    IF EXISTS (SELECT 1 FROM service_desk_policies WHERE id = governed_policy_id AND status = 'published') THEN
        RAISE EXCEPTION 'Published service-desk rosters are immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER service_desk_roster_immutable BEFORE INSERT OR UPDATE OR DELETE ON service_desk_roster_members FOR EACH ROW EXECUTE FUNCTION protect_published_service_desk_roster();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_desk_roster_members');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_published_service_desk_roster()');
    }
};
