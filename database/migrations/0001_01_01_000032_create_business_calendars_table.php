<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_calendars', function (Blueprint $table): void {
            $table->uuid('id')->primary('business_calendars_pkey');
            $table->string('code', 255);
            $table->integer('version');
            $table->string('name', 255);
            $table->string('timezone', 255);
            $table->jsonb('working_days');
            $table->time('workday_starts_at', 0);
            $table->time('workday_ends_at', 0);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 255)->default('draft');
            $table->uuid('created_by');
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at', 0)->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['code', 'version'], 'business_calendars_code_version_unique');
            $table->foreign(['created_by'], 'business_calendars_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['published_by'], 'business_calendars_published_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."business_calendars" ADD CONSTRAINT "business_calendars_effective_dates_check" CHECK (effective_to IS NULL OR effective_to > effective_from);
ALTER TABLE public."business_calendars" ADD CONSTRAINT "business_calendars_hours_check" CHECK (workday_ends_at > workday_starts_at);
ALTER TABLE public."business_calendars" ADD CONSTRAINT "business_calendars_status_check" CHECK (status::text = ANY (ARRAY['draft'::character varying::text, 'published'::character varying::text, 'retired'::character varying::text]));
CREATE INDEX business_calendars_status_effective_from_effective_to_index ON public.business_calendars USING btree (status, effective_from, effective_to);
CREATE TRIGGER business_calendars_immutable_after_publish BEFORE DELETE OR UPDATE ON business_calendars FOR EACH ROW EXECUTE FUNCTION prevent_published_business_calendar_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendars');
    }
};
