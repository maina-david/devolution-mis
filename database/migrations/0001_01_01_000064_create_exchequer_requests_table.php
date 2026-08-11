<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchequer_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('county_grant_id');
            $table->uuid('county_id');
            $table->uuid('created_by');
            $table->string('request_reference', 255);
            $table->string('tranche_reference', 255);
            $table->string('financial_year', 255);
            $table->decimal('amount', 16, 2);
            $table->char('currency', 3);
            $table->string('current_stage', 255)->default('prepared');
            $table->string('status', 255)->default('open');
            $table->timestampTz('stage_due_at', 0)->nullable();
            $table->timestampTz('last_event_at', 0)->nullable();
            $table->timestampTz('credited_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['county_grant_id', 'tranche_reference'], 'exchequer_requests_county_grant_id_tranche_reference_unique');
            $table->unique(['request_reference'], 'exchequer_requests_request_reference_unique');
            $table->foreign(['county_grant_id'], 'exchequer_requests_county_grant_id_foreign')
                ->references(['id'])
                ->on('county_grants')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'exchequer_requests_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'exchequer_requests_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'exchequer_requests_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."exchequer_requests" ADD CONSTRAINT "exchequer_request_amount_check" CHECK (amount > 0::numeric);
ALTER TABLE public."exchequer_requests" ADD CONSTRAINT "exchequer_request_currency_check" CHECK (currency = 'KES'::bpchar);
ALTER TABLE public."exchequer_requests" ADD CONSTRAINT "exchequer_request_stage_check" CHECK (current_stage::text = ANY (ARRAY['prepared'::character varying::text, 'submitted_to_treasury'::character varying::text, 'forwarded_to_ocob'::character varying::text, 'authorized_by_ocob'::character varying::text, 'issued_to_cbk'::character varying::text, 'credited'::character varying::text, 'returned'::character varying::text, 'exception'::character varying::text]));
ALTER TABLE public."exchequer_requests" ADD CONSTRAINT "exchequer_request_status_check" CHECK (status::text = ANY (ARRAY['open'::character varying::text, 'completed'::character varying::text, 'returned'::character varying::text, 'exception'::character varying::text]));
CREATE INDEX exchequer_requests_county_id_status_stage_due_at_index ON public.exchequer_requests USING btree (county_id, status, stage_due_at);
CREATE INDEX exchequer_requests_financial_year_current_stage_index ON public.exchequer_requests USING btree (financial_year, current_stage);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('exchequer_requests');
    }
};
