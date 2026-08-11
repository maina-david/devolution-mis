<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_delegations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('requested_by');
            $table->uuid('beneficiary_id');
            $table->uuid('approved_by')->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->string('reference', 255);
            $table->string('access_type', 30);
            $table->string('scope_type', 30);
            $table->jsonb('permission_scope');
            $table->jsonb('county_scope_snapshot');
            $table->text('business_justification');
            $table->string('incident_reference', 255)->nullable();
            $table->text('compensating_controls')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestampTz('starts_at', 0);
            $table->timestampTz('expires_at', 0);
            $table->timestampTz('approved_at', 0)->nullable();
            $table->timestampTz('activated_at', 0)->nullable();
            $table->timestampTz('expired_at', 0)->nullable();
            $table->timestampTz('revoked_at', 0)->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->text('decision_rationale')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->string('post_use_outcome', 30)->nullable();
            $table->text('post_use_findings')->nullable();
            $table->char('approval_checksum', 64)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['reference'], 'access_delegations_reference_unique');
            $table->foreign(['approved_by'], 'access_delegations_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['beneficiary_id'], 'access_delegations_beneficiary_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'access_delegations_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requested_by'], 'access_delegations_requested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'access_delegations_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['revoked_by'], 'access_delegations_revoked_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."access_delegations" ADD CONSTRAINT "access_delegations_dates_check" CHECK (expires_at > starts_at);
ALTER TABLE public."access_delegations" ADD CONSTRAINT "access_delegations_outcome_check" CHECK (post_use_outcome IS NULL OR (post_use_outcome::text = ANY (ARRAY['appropriate'::character varying::text, 'exception_noted'::character varying::text, 'investigation_required'::character varying::text])));
ALTER TABLE public."access_delegations" ADD CONSTRAINT "access_delegations_scope_check" CHECK (scope_type::text = ANY (ARRAY['county_portfolio'::character varying::text, 'national'::character varying::text]));
ALTER TABLE public."access_delegations" ADD CONSTRAINT "access_delegations_status_check" CHECK (status::text = ANY (ARRAY['pending'::character varying::text, 'scheduled'::character varying::text, 'active'::character varying::text, 'rejected'::character varying::text, 'expired'::character varying::text, 'revoked'::character varying::text, 'review_pending'::character varying::text, 'reviewed'::character varying::text]));
ALTER TABLE public."access_delegations" ADD CONSTRAINT "access_delegations_type_check" CHECK (access_type::text = ANY (ARRAY['delegated'::character varying::text, 'emergency'::character varying::text]));
CREATE INDEX access_delegations_access_type_status_index ON public.access_delegations USING btree (access_type, status);
CREATE INDEX access_delegations_beneficiary_id_status_starts_at_expires_at_i ON public.access_delegations USING btree (beneficiary_id, status, starts_at, expires_at);
CREATE INDEX access_delegations_status_expires_at_index ON public.access_delegations USING btree (status, expires_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('access_delegations');
    }
};
