<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_resolution_gaps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('igr_resolution_id');
            $table->uuid('igr_gap_category_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('owner_user_id');
            $table->string('title', 255);
            $table->text('description');
            $table->text('impact');
            $table->string('severity', 255);
            $table->string('status', 255)->default('open');
            $table->date('due_on');
            $table->text('mitigation_plan')->nullable();
            $table->text('resolution_note')->nullable();
            $table->uuid('reported_by');
            $table->uuid('resolved_by')->nullable();
            $table->uuid('accepted_by')->nullable();
            $table->timestampTz('mitigation_started_at', 0)->nullable();
            $table->timestampTz('resolved_at', 0)->nullable();
            $table->timestampTz('accepted_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['accepted_by'], 'igr_resolution_gaps_accepted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'igr_resolution_gaps_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['igr_gap_category_id'], 'igr_resolution_gaps_igr_gap_category_id_foreign')
                ->references(['id'])
                ->on('igr_gap_categories')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['igr_resolution_id'], 'igr_resolution_gaps_igr_resolution_id_foreign')
                ->references(['id'])
                ->on('igr_resolutions')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['owner_user_id'], 'igr_resolution_gaps_owner_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reported_by'], 'igr_resolution_gaps_reported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['resolved_by'], 'igr_resolution_gaps_resolved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_resolution_gaps_county_id_status_due_on_index ON public.igr_resolution_gaps USING btree (county_id, status, due_on);
CREATE INDEX igr_resolution_gaps_due_on_index ON public.igr_resolution_gaps USING btree (due_on);
CREATE INDEX igr_resolution_gaps_igr_resolution_id_status_index ON public.igr_resolution_gaps USING btree (igr_resolution_id, status);
CREATE INDEX igr_resolution_gaps_severity_index ON public.igr_resolution_gaps USING btree (severity);
CREATE INDEX igr_resolution_gaps_status_index ON public.igr_resolution_gaps USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_resolution_gaps');
    }
};
