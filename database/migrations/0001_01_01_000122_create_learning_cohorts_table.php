<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_cohorts', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_cohorts_pkey');
            $table->uuid('learning_course_id');
            $table->uuid('instructor_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('created_by');
            $table->string('code', 80);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('capacity');
            $table->date('enrollment_opens_on');
            $table->date('enrollment_closes_on');
            $table->timestampTz('starts_at', 0);
            $table->timestampTz('ends_at', 0);
            $table->string('status', 30)->default('draft');
            $table->uuid('transitioned_by')->nullable();
            $table->timestampTz('transitioned_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'learning_cohorts_code_unique');
            $table->foreign(['county_id'], 'learning_cohorts_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'learning_cohorts_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['instructor_id'], 'learning_cohorts_instructor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_course_id'], 'learning_cohorts_learning_course_id_foreign')
                ->references(['id'])
                ->on('learning_courses')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['transitioned_by'], 'learning_cohorts_transitioned_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."learning_cohorts" ADD CONSTRAINT "learning_cohorts_capacity_check" CHECK (capacity > 0);
ALTER TABLE public."learning_cohorts" ADD CONSTRAINT "learning_cohorts_delivery_window_check" CHECK (starts_at > enrollment_closes_on AND ends_at > starts_at);
ALTER TABLE public."learning_cohorts" ADD CONSTRAINT "learning_cohorts_enrollment_window_check" CHECK (enrollment_closes_on >= enrollment_opens_on);
ALTER TABLE public."learning_cohorts" ADD CONSTRAINT "learning_cohorts_status_check" CHECK (status::text = ANY (ARRAY['draft'::character varying::text, 'open'::character varying::text, 'active'::character varying::text, 'completed'::character varying::text, 'cancelled'::character varying::text]));
ALTER TABLE public."learning_cohorts" ADD CONSTRAINT "learning_cohorts_transition_evidence_check" CHECK (status::text = 'draft'::text AND transitioned_by IS NULL AND transitioned_at IS NULL OR status::text <> 'draft'::text AND transitioned_by IS NOT NULL AND transitioned_at IS NOT NULL);
CREATE INDEX learning_cohorts_county_id_status_starts_at_index ON public.learning_cohorts USING btree (county_id, status, starts_at);
CREATE INDEX learning_cohorts_learning_course_id_status_index ON public.learning_cohorts USING btree (learning_course_id, status);
CREATE INDEX learning_cohorts_status_index ON public.learning_cohorts USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_cohorts');
    }
};
