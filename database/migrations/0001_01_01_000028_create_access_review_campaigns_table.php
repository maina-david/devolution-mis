<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_review_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary('access_review_campaigns_pkey');
            $table->uuid('launched_by')->nullable();
            $table->uuid('reviewer_id')->nullable();
            $table->string('reference', 255);
            $table->string('name', 255);
            $table->text('scope');
            $table->jsonb('role_scope');
            $table->string('status', 30)->default('open');
            $table->date('period_from');
            $table->date('period_to');
            $table->timestampTz('due_at', 0);
            $table->timestampTz('launched_at', 0);
            $table->timestampTz('completed_at', 0)->nullable();
            $table->integer('item_count')->default(0);
            $table->integer('retained_count')->default(0);
            $table->integer('revoked_count')->default(0);
            $table->integer('remediation_count')->default(0);
            $table->char('evidence_checksum', 64)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['reference'], 'access_review_campaigns_reference_unique');
            $table->foreign(['launched_by'], 'access_review_campaigns_launched_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reviewer_id'], 'access_review_campaigns_reviewer_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX access_review_campaigns_status_due_at_index ON public.access_review_campaigns USING btree (status, due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('access_review_campaigns');
    }
};
