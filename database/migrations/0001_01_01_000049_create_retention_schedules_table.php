<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary('retention_schedules_pkey');
            $table->uuid('approved_by')->nullable();
            $table->string('code', 255);
            $table->string('record_class', 255);
            $table->text('trigger_event');
            $table->integer('retention_months');
            $table->string('disposition_action', 30);
            $table->text('legal_authority');
            $table->text('legal_hold_rule');
            $table->string('status', 30)->default('draft');
            $table->timestampTz('effective_from', 0)->nullable();
            $table->timestampTz('approved_at', 0)->nullable();
            $table->date('next_review_at')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['code'], 'retention_schedules_code_unique');
            $table->foreign(['approved_by'], 'retention_schedules_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX retention_schedules_status_effective_from_index ON public.retention_schedules USING btree (status, effective_from);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_schedules');
    }
};
