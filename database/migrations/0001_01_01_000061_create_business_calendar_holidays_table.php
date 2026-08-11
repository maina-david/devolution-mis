<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_calendar_holidays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('business_calendar_id');
            $table->date('holiday_date');
            $table->string('name', 255);
            $table->string('category', 255)->default('public_holiday');
            $table->string('source_reference', 255);
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['business_calendar_id', 'holiday_date'], 'business_calendar_holidays_business_calendar_id_holiday_date_un');
            $table->foreign(['business_calendar_id'], 'business_calendar_holidays_business_calendar_id_foreign')
                ->references(['id'])
                ->on('business_calendars')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'business_calendar_holidays_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."business_calendar_holidays" ADD CONSTRAINT "business_calendar_holidays_category_check" CHECK (category::text = ANY (ARRAY['public_holiday'::character varying::text, 'government_closure'::character varying::text, 'exception'::character varying::text]));
CREATE INDEX business_calendar_holidays_holiday_date_category_index ON public.business_calendar_holidays USING btree (holiday_date, category);
CREATE TRIGGER business_calendar_holidays_immutable_after_publish BEFORE INSERT OR DELETE OR UPDATE ON business_calendar_holidays FOR EACH ROW EXECUTE FUNCTION prevent_published_business_calendar_holiday_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendar_holidays');
    }
};
