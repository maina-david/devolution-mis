<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary('processing_activities_pkey');
            $table->uuid('data_asset_id');
            $table->uuid('retention_schedule_id')->nullable();
            $table->uuid('submitted_by')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->string('reference', 255);
            $table->string('name', 255);
            $table->text('purpose');
            $table->string('lawful_basis', 50);
            $table->text('lawful_basis_reference');
            $table->string('controller_name', 255);
            $table->jsonb('processor_names')->nullable();
            $table->jsonb('recipient_categories')->nullable();
            $table->jsonb('processing_operations');
            $table->boolean('automated_decision_making')->default(false);
            $table->boolean('cross_border_transfer')->default(false);
            $table->jsonb('transfer_countries')->nullable();
            $table->text('transfer_safeguards')->nullable();
            $table->string('dpia_status', 30)->default('screening_required');
            $table->string('dpia_reference', 255)->nullable();
            $table->text('risk_summary')->nullable();
            $table->text('security_measures');
            $table->string('status', 30)->default('draft');
            $table->timestampTz('submitted_at', 0)->nullable();
            $table->timestampTz('reviewed_at', 0)->nullable();
            $table->date('next_review_at')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['reference'], 'processing_activities_reference_unique');
            $table->foreign(['data_asset_id'], 'processing_activities_data_asset_id_foreign')
                ->references(['id'])
                ->on('data_assets')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['retention_schedule_id'], 'processing_activities_retention_schedule_id_foreign')
                ->references(['id'])
                ->on('retention_schedules')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reviewed_by'], 'processing_activities_reviewed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'processing_activities_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX processing_activities_data_asset_id_status_index ON public.processing_activities USING btree (data_asset_id, status);
CREATE INDEX processing_activities_status_dpia_status_index ON public.processing_activities USING btree (status, dpia_status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_activities');
    }
};
