<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchequer_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('exchequer_request_id');
            $table->uuid('integration_exchange_id')->nullable();
            $table->uuid('recorded_by');
            $table->string('source_system', 255);
            $table->string('event_type', 255);
            $table->string('source_event_reference', 255);
            $table->timestampTz('occurred_at', 0);
            $table->timestampTz('received_at', 0);
            $table->integer('elapsed_from_previous_minutes');
            $table->integer('elapsed_total_minutes');
            $table->text('notes')->nullable();
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['source_system', 'source_event_reference'], 'exchequer_events_source_system_source_event_reference_unique');
            $table->foreign(['exchequer_request_id'], 'exchequer_events_exchequer_request_id_foreign')
                ->references(['id'])
                ->on('exchequer_requests')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['integration_exchange_id'], 'exchequer_events_integration_exchange_id_foreign')
                ->references(['id'])
                ->on('integration_exchanges')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['recorded_by'], 'exchequer_events_recorded_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."exchequer_events" ADD CONSTRAINT "exchequer_event_source_check" CHECK (source_system::text = ANY (ARRAY['IDMIS'::character varying::text, 'TREASURY'::character varying::text, 'OCOB'::character varying::text, 'CBK'::character varying::text]));
ALTER TABLE public."exchequer_events" ADD CONSTRAINT "exchequer_event_type_check" CHECK (event_type::text = ANY (ARRAY['submitted_to_treasury'::character varying::text, 'treasury_forwarded_ocob'::character varying::text, 'ocob_authorized'::character varying::text, 'treasury_issued_cbk'::character varying::text, 'cbk_credited'::character varying::text, 'returned'::character varying::text, 'exception'::character varying::text]));
CREATE INDEX exchequer_events_event_type_occurred_at_index ON public.exchequer_events USING btree (event_type, occurred_at);
CREATE INDEX exchequer_events_exchequer_request_id_occurred_at_index ON public.exchequer_events USING btree (exchequer_request_id, occurred_at);
CREATE TRIGGER exchequer_events_immutable BEFORE DELETE OR UPDATE ON exchequer_events FOR EACH ROW EXECUTE FUNCTION reject_exchequer_event_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('exchequer_events');
    }
};
