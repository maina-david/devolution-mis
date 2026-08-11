<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_collaboration_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_collaboration_plans_pkey');
            $table->uuid('partner_profile_id');
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->text('objective');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 255)->default('draft');
            $table->uuid('created_by');
            $table->uuid('submitted_by')->nullable();
            $table->timestampTz('submitted_at', 0)->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at', 0)->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['reference'], 'partner_collaboration_plans_reference_unique');
            $table->foreign(['approved_by'], 'partner_collaboration_plans_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'partner_collaboration_plans_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['partner_profile_id'], 'partner_collaboration_plans_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'partner_collaboration_plans_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_collaboration_plans_ends_on_index ON public.partner_collaboration_plans USING btree (ends_on);
CREATE INDEX partner_collaboration_plans_starts_on_index ON public.partner_collaboration_plans USING btree (starts_on);
CREATE INDEX partner_collaboration_plans_status_index ON public.partner_collaboration_plans USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_collaboration_plans');
    }
};
