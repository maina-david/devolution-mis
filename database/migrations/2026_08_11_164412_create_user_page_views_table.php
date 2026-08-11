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
        Schema::create('user_page_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('user_activity_session_id')->nullable();
            $table->uuid('team_id')->nullable();
            $table->string('route_name');
            $table->text('path');
            $table->string('page_title');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('viewed_at');
            $table->timestampsTz();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('user_activity_session_id')->references('id')->on('user_activity_sessions')->restrictOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->index(['user_id', 'viewed_at']);
            $table->index(['user_activity_session_id', 'viewed_at']);
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_user_page_views() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'user_page_views is append-only';
END;
$$;
CREATE TRIGGER user_page_views_append_only BEFORE UPDATE OR DELETE ON user_page_views FOR EACH ROW EXECUTE FUNCTION protect_user_page_views();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_page_views');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_user_page_views()');
    }
};
