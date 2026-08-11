<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_resolution_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('igr_resolution_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('responsibility_role', 255)->default('implementer');
            $table->boolean('is_lead')->default(false);
            $table->string('status', 255)->default('active');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['igr_resolution_id', 'user_id', 'county_id'], 'igr_resolution_assignment_unique');
            $table->foreign(['county_id'], 'igr_resolution_assignments_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['igr_resolution_id'], 'igr_resolution_assignments_igr_resolution_id_foreign')
                ->references(['id'])
                ->on('igr_resolutions')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['organization_id'], 'igr_resolution_assignments_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'igr_resolution_assignments_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_resolution_assignments_status_index ON public.igr_resolution_assignments USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_resolution_assignments');
    }
};
