<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_review_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('access_review_campaign_id');
            $table->uuid('user_id')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->uuid('reinstated_by')->nullable();
            $table->string('role_name', 255);
            $table->jsonb('permission_snapshot');
            $table->uuid('home_county_id')->nullable();
            $table->jsonb('assigned_county_snapshot');
            $table->boolean('mfa_enabled')->default(false);
            $table->boolean('passkey_enabled')->default(false);
            $table->timestampTz('last_authenticated_at', 0)->nullable();
            $table->string('decision', 30)->default('pending');
            $table->text('rationale')->nullable();
            $table->text('remediation_action')->nullable();
            $table->date('remediation_due_at')->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->timestampTz('revoked_at', 0)->nullable();
            $table->integer('sessions_revoked')->default(0);
            $table->timestampTz('reinstated_at', 0)->nullable();
            $table->text('reinstatement_rationale')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['access_review_campaign_id', 'user_id'], 'access_review_items_access_review_campaign_id_user_id_unique');
            $table->foreign(['access_review_campaign_id'], 'access_review_items_access_review_campaign_id_foreign')
                ->references(['id'])
                ->on('access_review_campaigns')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['home_county_id'], 'access_review_items_home_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reinstated_by'], 'access_review_items_reinstated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'access_review_items_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'access_review_items_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX access_review_items_decision_remediation_due_at_index ON public.access_review_items USING btree (decision, remediation_due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_items');
    }
};
