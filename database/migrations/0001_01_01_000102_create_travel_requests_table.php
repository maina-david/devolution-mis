<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('workflow_instance_id')->nullable();
            $table->string('reference', 255);
            $table->uuid('requester_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('organization_id')->nullable();
            $table->uuid('sector_id')->nullable();
            $table->string('travel_type', 255);
            $table->string('purpose', 255);
            $table->text('justification');
            $table->string('destination_country', 255);
            $table->string('destination_county', 255)->nullable();
            $table->string('destination_city', 255);
            $table->date('departure_date');
            $table->date('return_date');
            $table->decimal('estimated_cost', 18, 2);
            $table->char('currency', 3);
            $table->string('funding_source', 255);
            $table->string('cost_centre', 255)->nullable();
            $table->string('hris_employee_reference', 255)->nullable();
            $table->string('finance_commitment_reference', 255)->nullable();
            $table->string('integration_status', 255)->default('pending');
            $table->jsonb('integration_metadata')->nullable();
            $table->string('status', 255)->default('draft');
            $table->string('priority', 255)->default('normal');
            $table->timestamp('submitted_at', 0)->nullable();
            $table->timestamp('decision_due_at', 0)->nullable();
            $table->timestamp('decided_at', 0)->nullable();
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['reference'], 'travel_requests_reference_unique');
            $table->foreign(['county_id'], 'travel_requests_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'travel_requests_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['organization_id'], 'travel_requests_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'travel_requests_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['requester_id'], 'travel_requests_requester_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'travel_requests_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'travel_requests_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX travel_requests_county_id_status_departure_date_index ON public.travel_requests USING btree (county_id, status, departure_date);
CREATE INDEX travel_requests_decision_due_at_index ON public.travel_requests USING btree (decision_due_at);
CREATE INDEX travel_requests_departure_date_index ON public.travel_requests USING btree (departure_date);
CREATE INDEX travel_requests_finance_commitment_reference_index ON public.travel_requests USING btree (finance_commitment_reference);
CREATE INDEX travel_requests_hris_employee_reference_index ON public.travel_requests USING btree (hris_employee_reference);
CREATE INDEX travel_requests_integration_status_index ON public.travel_requests USING btree (integration_status);
CREATE INDEX travel_requests_priority_index ON public.travel_requests USING btree (priority);
CREATE INDEX travel_requests_requester_id_status_index ON public.travel_requests USING btree (requester_id, status);
CREATE INDEX travel_requests_return_date_index ON public.travel_requests USING btree (return_date);
CREATE INDEX travel_requests_status_decision_due_at_reminder_sent_at_index ON public.travel_requests USING btree (status, decision_due_at, reminder_sent_at);
CREATE INDEX travel_requests_status_index ON public.travel_requests USING btree (status);
CREATE INDEX travel_requests_travel_type_index ON public.travel_requests USING btree (travel_type);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_requests');
    }
};
