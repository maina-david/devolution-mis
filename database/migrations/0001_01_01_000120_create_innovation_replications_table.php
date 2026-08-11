<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('innovation_replications', function (Blueprint $table): void {
            $table->uuid('id')->primary('innovation_replications_pkey');
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('devolution_innovation_id');
            $table->uuid('source_county_id');
            $table->uuid('target_county_id');
            $table->uuid('accountable_user_id');
            $table->uuid('created_by');
            $table->uuid('submitted_by')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->string('reference', 80);
            $table->text('adaptation_plan');
            $table->string('success_measure', 255);
            $table->decimal('baseline_value', 18, 4);
            $table->decimal('target_value', 18, 4);
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->date('starts_on');
            $table->date('target_completion_on');
            $table->text('outcome_summary')->nullable();
            $table->string('status', 30)->default('planned');
            $table->string('verification_decision', 30)->default('pending');
            $table->text('verification_rationale')->nullable();
            $table->char('decision_checksum', 64)->nullable();
            $table->timestampTz('submitted_at', 0)->nullable();
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['devolution_innovation_id', 'target_county_id'], 'innovation_replications_devolution_innovation_id_target_county_');
            $table->unique(['reference'], 'innovation_replications_reference_unique');
            $table->foreign(['accountable_user_id'], 'innovation_replications_accountable_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'innovation_replications_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['devolution_innovation_id'], 'innovation_replications_devolution_innovation_id_foreign')
                ->references(['id'])
                ->on('devolution_innovations')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'innovation_replications_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['source_county_id'], 'innovation_replications_source_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'innovation_replications_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['target_county_id'], 'innovation_replications_target_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['verified_by'], 'innovation_replications_verified_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'innovation_replications_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."innovation_replications" ADD CONSTRAINT "innovation_replications_counties_check" CHECK (source_county_id <> target_county_id);
ALTER TABLE public."innovation_replications" ADD CONSTRAINT "innovation_replications_dates_check" CHECK (target_completion_on >= starts_on);
ALTER TABLE public."innovation_replications" ADD CONSTRAINT "innovation_replications_decision_check" CHECK (verification_decision::text = ANY (ARRAY['pending'::character varying::text, 'approved'::character varying::text, 'returned'::character varying::text]));
ALTER TABLE public."innovation_replications" ADD CONSTRAINT "innovation_replications_status_check" CHECK (status::text = ANY (ARRAY['planned'::character varying::text, 'adapting'::character varying::text, 'piloting'::character varying::text, 'verification'::character varying::text, 'adopted'::character varying::text, 'abandoned'::character varying::text]));
CREATE INDEX innovation_replications_accountable_user_id_status_index ON public.innovation_replications USING btree (accountable_user_id, status);
CREATE INDEX innovation_replications_status_index ON public.innovation_replications USING btree (status);
CREATE INDEX innovation_replications_target_status_due_index ON public.innovation_replications USING btree (target_county_id, status, target_completion_on);
CREATE TRIGGER innovation_replications_terminal_immutable BEFORE DELETE OR UPDATE ON innovation_replications FOR EACH ROW EXECUTE FUNCTION protect_terminal_innovation_replication();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('innovation_replications');
    }
};
