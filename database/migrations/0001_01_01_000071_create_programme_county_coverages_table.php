<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_county_coverages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('programme_id');
            $table->uuid('county_id');
            $table->uuid('implementation_lead_id')->nullable();
            $table->uuid('created_by');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 30)->default('planned');
            $table->decimal('funding_allocation', 20, 2)->nullable();
            $table->string('currency', 3);
            $table->string('source_reference', 255);
            $table->text('notes')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['county_id'], 'programme_county_coverages_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'programme_county_coverages_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['implementation_lead_id'], 'programme_county_coverages_implementation_lead_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['programme_id'], 'programme_county_coverages_programme_id_foreign')
                ->references(['id'])
                ->on('programmes')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."programme_county_coverages" ADD CONSTRAINT "programme_county_coverages_currency_check" CHECK (currency::text ~ '^[A-Z]{3}$'::text);
ALTER TABLE public."programme_county_coverages" ADD CONSTRAINT "programme_county_coverages_dates_check" CHECK (ends_on IS NULL OR ends_on >= starts_on);
ALTER TABLE public."programme_county_coverages" ADD CONSTRAINT "programme_county_coverages_funding_check" CHECK (funding_allocation IS NULL OR funding_allocation >= 0::numeric);
ALTER TABLE public."programme_county_coverages" ADD CONSTRAINT "programme_county_coverages_no_overlap" EXCLUDE USING gist (programme_id WITH =, county_id WITH =, daterange(starts_on, COALESCE(ends_on, 'infinity'::date), '[]'::text) WITH &&) WHERE (deleted_at IS NULL);
ALTER TABLE public."programme_county_coverages" ADD CONSTRAINT "programme_county_coverages_status_check" CHECK (status::text = ANY (ARRAY['planned'::character varying::text, 'active'::character varying::text, 'paused'::character varying::text, 'closed'::character varying::text]));
CREATE INDEX programme_coverage_county_status_start_index ON public.programme_county_coverages USING btree (county_id, status, starts_on);
CREATE INDEX programme_coverage_programme_status_start_index ON public.programme_county_coverages USING btree (programme_id, status, starts_on);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_county_coverages');
    }
};
