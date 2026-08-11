<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizen_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary('citizen_cases_pkey');
            $table->uuid('workflow_instance_id')->nullable();
            $table->string('reference', 255);
            $table->string('tracking_token_hash', 64);
            $table->string('case_type', 255);
            $table->string('category', 255);
            $table->string('channel', 255);
            $table->uuid('county_id');
            $table->uuid('sector_id')->nullable();
            $table->string('subject', 255);
            $table->text('description');
            $table->text('citizen_name')->nullable();
            $table->text('citizen_email')->nullable();
            $table->text('citizen_phone')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('preferred_contact', 255)->default('none');
            $table->text('accessibility_needs')->nullable();
            $table->boolean('consent_given');
            $table->timestamp('consent_recorded_at', 0);
            $table->string('privacy_notice_version', 255);
            $table->string('priority', 255)->default('medium');
            $table->string('status', 255)->default('received');
            $table->boolean('is_sensitive')->default(false);
            $table->uuid('assigned_to')->nullable();
            $table->uuid('assigned_organization_id')->nullable();
            $table->timestamp('first_response_due_at', 0);
            $table->timestamp('resolution_due_at', 0);
            $table->timestamp('first_responded_at', 0)->nullable();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolved_at', 0)->nullable();
            $table->smallInteger('satisfaction_rating')->nullable();
            $table->text('satisfaction_comment')->nullable();
            $table->timestamp('satisfaction_recorded_at', 0)->nullable();
            $table->jsonb('source_metadata')->nullable();
            $table->timestamp('reminder_sent_at', 0)->nullable();
            $table->timestamp('escalated_at', 0)->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('intake_reference_data_release_id')->nullable();
            $table->uuid('triage_reference_data_release_id')->nullable();
            $table->unique(['reference'], 'citizen_cases_reference_unique');
            $table->unique(['tracking_token_hash'], 'citizen_cases_tracking_token_hash_unique');
            $table->foreign(['assigned_organization_id'], 'citizen_cases_assigned_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['assigned_to'], 'citizen_cases_assigned_to_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'citizen_cases_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'citizen_cases_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['intake_reference_data_release_id'], 'citizen_cases_intake_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'citizen_cases_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['triage_reference_data_release_id'], 'citizen_cases_triage_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'citizen_cases_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX citizen_cases_assigned_to_status_resolution_due_at_index ON public.citizen_cases USING btree (assigned_to, status, resolution_due_at);
CREATE INDEX citizen_cases_case_type_index ON public.citizen_cases USING btree (case_type);
CREATE INDEX citizen_cases_category_index ON public.citizen_cases USING btree (category);
CREATE INDEX citizen_cases_channel_index ON public.citizen_cases USING btree (channel);
CREATE INDEX citizen_cases_county_id_case_type_status_created_at_index ON public.citizen_cases USING btree (county_id, case_type, status, created_at);
CREATE INDEX citizen_cases_first_response_due_at_index ON public.citizen_cases USING btree (first_response_due_at);
CREATE INDEX citizen_cases_is_sensitive_index ON public.citizen_cases USING btree (is_sensitive);
CREATE INDEX citizen_cases_priority_index ON public.citizen_cases USING btree (priority);
CREATE INDEX citizen_cases_resolution_due_at_index ON public.citizen_cases USING btree (resolution_due_at);
CREATE INDEX citizen_cases_status_index ON public.citizen_cases USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_cases');
    }
};
