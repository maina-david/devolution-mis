<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_collaboration_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_collaboration_actions_pkey');
            $table->uuid('partner_collaboration_plan_id');
            $table->uuid('county_id');
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description');
            $table->uuid('accountable_user_id');
            $table->uuid('accountable_organization_id')->nullable();
            $table->date('due_on');
            $table->decimal('progress_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('status', 255)->default('open');
            $table->uuid('created_by');
            $table->timestampTz('verified_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->timestampTz('reminder_sent_at', 0)->nullable();
            $table->timestampTz('escalated_at', 0)->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['partner_collaboration_plan_id', 'code'], 'partner_collaboration_action_code_unique');
            $table->foreign(['accountable_organization_id'], 'partner_collaboration_actions_accountable_organization_id_forei')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['accountable_user_id'], 'partner_collaboration_actions_accountable_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'partner_collaboration_actions_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'partner_collaboration_actions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['partner_collaboration_plan_id'], 'partner_collaboration_actions_partner_collaboration_plan_id_for')
                ->references(['id'])
                ->on('partner_collaboration_plans')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'partner_collaboration_actions_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_collaboration_actions_due_on_index ON public.partner_collaboration_actions USING btree (due_on);
CREATE INDEX partner_collaboration_actions_escalated_at_index ON public.partner_collaboration_actions USING btree (escalated_at);
CREATE INDEX partner_collaboration_actions_reminder_sent_at_index ON public.partner_collaboration_actions USING btree (reminder_sent_at);
CREATE INDEX partner_collaboration_actions_status_index ON public.partner_collaboration_actions USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_collaboration_actions');
    }
};
