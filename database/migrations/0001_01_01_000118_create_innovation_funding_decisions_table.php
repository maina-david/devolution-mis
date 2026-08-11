<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('innovation_funding_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('devolution_innovation_id');
            $table->integer('decision_version');
            $table->string('decision', 255);
            $table->decimal('amount', 18, 2)->default(DB::raw('\'0\'::numeric'));
            $table->char('currency', 3);
            $table->string('funding_type', 255);
            $table->string('decision_reference', 255);
            $table->text('rationale');
            $table->uuid('decided_by');
            $table->timestamp('decided_at', 0);
            $table->char('previous_checksum', 64)->nullable();
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['decision_reference'], 'innovation_funding_decisions_decision_reference_unique');
            $table->unique(['devolution_innovation_id', 'decision_version'], 'innovation_funding_version_unique');
            $table->foreign(['decided_by'], 'innovation_funding_decisions_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['devolution_innovation_id'], 'innovation_funding_decisions_devolution_innovation_id_foreign')
                ->references(['id'])
                ->on('devolution_innovations')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."innovation_funding_decisions" ADD CONSTRAINT "innovation_funding_amount_check" CHECK (decision::text = 'approved'::text AND amount > 0::numeric AND funding_type::text <> 'not_applicable'::text OR (decision::text = ANY (ARRAY['declined'::character varying::text, 'not_required'::character varying::text])) AND amount = 0::numeric);
ALTER TABLE public."innovation_funding_decisions" ADD CONSTRAINT "innovation_funding_decision_check" CHECK (decision::text = ANY (ARRAY['approved'::character varying::text, 'declined'::character varying::text, 'not_required'::character varying::text]));
ALTER TABLE public."innovation_funding_decisions" ADD CONSTRAINT "innovation_funding_type_check" CHECK (funding_type::text = ANY (ARRAY['grant'::character varying::text, 'in_kind'::character varying::text, 'blended'::character varying::text, 'not_applicable'::character varying::text]));
CREATE TRIGGER innovation_funding_decisions_immutable BEFORE DELETE OR UPDATE ON innovation_funding_decisions FOR EACH ROW EXECUTE FUNCTION reject_innovation_funding_decision_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('innovation_funding_decisions');
    }
};
