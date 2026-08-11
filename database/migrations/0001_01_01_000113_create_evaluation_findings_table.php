<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_findings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('programme_evaluation_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('accountable_owner_id');
            $table->uuid('created_by');
            $table->uuid('closed_by')->nullable();
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->text('finding');
            $table->text('recommendation');
            $table->string('severity', 255);
            $table->string('status', 255)->default('open');
            $table->date('due_at');
            $table->decimal('progress_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->text('closure_note')->nullable();
            $table->timestampTz('closed_at', 0)->nullable();
            $table->string('checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->unique(['reference'], 'evaluation_findings_reference_unique');
            $table->foreign(['accountable_owner_id'], 'evaluation_findings_accountable_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['closed_by'], 'evaluation_findings_closed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'evaluation_findings_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'evaluation_findings_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['programme_evaluation_id'], 'evaluation_findings_programme_evaluation_id_foreign')
                ->references(['id'])
                ->on('programme_evaluations')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX evaluation_findings_county_id_status_due_at_index ON public.evaluation_findings USING btree (county_id, status, due_at);
CREATE INDEX evaluation_findings_escalation_due_index ON public.evaluation_findings USING btree (status, due_at, escalated_at);
CREATE INDEX evaluation_findings_reminder_due_index ON public.evaluation_findings USING btree (status, due_at, reminder_sent_at);
CREATE TRIGGER evaluation_findings_closed_immutable BEFORE DELETE OR UPDATE ON evaluation_findings FOR EACH ROW EXECUTE FUNCTION protect_closed_evaluation_findings();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_findings');
    }
};
