<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_classroom_attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary('virtual_classroom_attendances_pkey');
            $table->uuid('virtual_classroom_id');
            $table->uuid('learning_enrollment_id');
            $table->uuid('user_id');
            $table->string('attendance_status', 255);
            $table->timestamp('joined_at', 0)->nullable();
            $table->timestamp('left_at', 0)->nullable();
            $table->integer('attended_minutes')->default(0);
            $table->string('source', 255)->default('manual');
            $table->string('provider_event_id', 255)->nullable();
            $table->char('payload_checksum', 64);
            $table->text('notes')->nullable();
            $table->uuid('recorded_by');
            $table->timestamp('recorded_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['virtual_classroom_id', 'learning_enrollment_id'], 'classroom_attendance_enrollment_unique');
            $table->unique(['virtual_classroom_id', 'provider_event_id'], 'classroom_attendance_provider_event_unique');
            $table->foreign(['learning_enrollment_id'], 'virtual_classroom_attendances_learning_enrollment_id_foreign')
                ->references(['id'])
                ->on('learning_enrollments')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['recorded_by'], 'virtual_classroom_attendances_recorded_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'virtual_classroom_attendances_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['virtual_classroom_id'], 'virtual_classroom_attendances_virtual_classroom_id_foreign')
                ->references(['id'])
                ->on('virtual_classrooms')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."virtual_classroom_attendances" ADD CONSTRAINT "classroom_attendance_provider_check" CHECK (source::text = 'provider_import'::text AND provider_event_id IS NOT NULL OR source::text = 'manual'::text AND provider_event_id IS NULL);
ALTER TABLE public."virtual_classroom_attendances" ADD CONSTRAINT "classroom_attendance_source_check" CHECK (source::text = ANY (ARRAY['manual'::character varying::text, 'provider_import'::character varying::text]));
ALTER TABLE public."virtual_classroom_attendances" ADD CONSTRAINT "classroom_attendance_status_check" CHECK (attendance_status::text = ANY (ARRAY['present'::character varying::text, 'partial'::character varying::text, 'absent'::character varying::text]));
ALTER TABLE public."virtual_classroom_attendances" ADD CONSTRAINT "classroom_attendance_time_check" CHECK (attendance_status::text = 'absent'::text AND joined_at IS NULL AND left_at IS NULL AND attended_minutes = 0 OR (attendance_status::text = ANY (ARRAY['present'::character varying::text, 'partial'::character varying::text])) AND joined_at IS NOT NULL AND left_at IS NOT NULL AND left_at > joined_at AND attended_minutes > 0);
CREATE INDEX classroom_attendance_status_index ON public.virtual_classroom_attendances USING btree (virtual_classroom_id, attendance_status);
CREATE INDEX virtual_classroom_attendances_attendance_status_index ON public.virtual_classroom_attendances USING btree (attendance_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_classroom_attendances');
    }
};
