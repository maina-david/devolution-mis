<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_community_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary('knowledge_community_reports_pkey');
            $table->uuid('workflow_instance_id')->nullable();
            $table->uuid('knowledge_post_id');
            $table->uuid('county_id')->nullable();
            $table->uuid('reported_by');
            $table->uuid('triaged_by')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->string('reference', 255);
            $table->string('category', 255);
            $table->string('severity', 255);
            $table->text('description');
            $table->string('status', 255)->default('reported');
            $table->text('resolution')->nullable();
            $table->timestamp('triaged_at', 0)->nullable();
            $table->timestamp('decided_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->string('post_action', 255)->nullable();
            $table->unique(['reference'], 'knowledge_community_reports_reference_unique');
            $table->foreign(['county_id'], 'knowledge_community_reports_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['decided_by'], 'knowledge_community_reports_decided_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['knowledge_post_id'], 'knowledge_community_reports_knowledge_post_id_foreign')
                ->references(['id'])
                ->on('knowledge_posts')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reported_by'], 'knowledge_community_reports_reported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['triaged_by'], 'knowledge_community_reports_triaged_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'knowledge_community_reports_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."knowledge_community_reports" ADD CONSTRAINT "knowledge_report_category_check" CHECK (category::text = ANY (ARRAY['misinformation'::character varying::text, 'harassment'::character varying::text, 'privacy'::character varying::text, 'security'::character varying::text, 'spam'::character varying::text, 'other'::character varying::text]));
ALTER TABLE public."knowledge_community_reports" ADD CONSTRAINT "knowledge_report_decision_check" CHECK ((status::text = ANY (ARRAY['reported'::character varying::text, 'investigating'::character varying::text])) AND decided_by IS NULL AND decided_at IS NULL AND resolution IS NULL OR (status::text = ANY (ARRAY['resolved'::character varying::text, 'dismissed'::character varying::text])) AND decided_by IS NOT NULL AND decided_at IS NOT NULL AND resolution IS NOT NULL);
ALTER TABLE public."knowledge_community_reports" ADD CONSTRAINT "knowledge_report_post_action_check" CHECK ((status::text = ANY (ARRAY['reported'::character varying::text, 'investigating'::character varying::text])) AND post_action IS NULL OR (status::text = ANY (ARRAY['resolved'::character varying::text, 'dismissed'::character varying::text])) AND (post_action::text = ANY (ARRAY['hide'::character varying::text, 'keep_visible'::character varying::text])));
ALTER TABLE public."knowledge_community_reports" ADD CONSTRAINT "knowledge_report_severity_check" CHECK (severity::text = ANY (ARRAY['low'::character varying::text, 'medium'::character varying::text, 'high'::character varying::text, 'critical'::character varying::text]));
ALTER TABLE public."knowledge_community_reports" ADD CONSTRAINT "knowledge_report_status_check" CHECK (status::text = ANY (ARRAY['reported'::character varying::text, 'investigating'::character varying::text, 'resolved'::character varying::text, 'dismissed'::character varying::text]));
CREATE INDEX knowledge_report_duplicate_index ON public.knowledge_community_reports USING btree (knowledge_post_id, reported_by, status);
CREATE INDEX knowledge_report_scope_status_index ON public.knowledge_community_reports USING btree (county_id, status, created_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_community_reports');
    }
};
