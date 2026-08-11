<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_resolutions', function (Blueprint $table): void {
            $table->uuid('id')->primary('igr_resolutions_pkey');
            $table->uuid('igr_forum_id');
            $table->uuid('workflow_instance_id')->nullable();
            $table->string('resolution_number', 255);
            $table->string('title', 255);
            $table->text('resolution_text');
            $table->date('resolved_on');
            $table->date('due_on');
            $table->string('priority', 255)->default('medium');
            $table->string('status', 255)->default('open');
            $table->smallInteger('progress_percentage')->default(DB::raw('\'0\'::smallint'));
            $table->text('implementation_gap')->nullable();
            $table->text('closure_evidence')->nullable();
            $table->uuid('created_by');
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at', 0)->nullable();
            $table->timestamp('reminder_sent_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('igr_forum_meeting_id')->nullable();
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['resolution_number'], 'igr_resolutions_resolution_number_unique');
            $table->foreign(['closed_by'], 'igr_resolutions_closed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'igr_resolutions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['igr_forum_id'], 'igr_resolutions_igr_forum_id_foreign')
                ->references(['id'])
                ->on('igr_forums')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['igr_forum_meeting_id'], 'igr_resolutions_igr_forum_meeting_id_foreign')
                ->references(['id'])
                ->on('igr_forum_meetings')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'igr_resolutions_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'igr_resolutions_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_resolutions_due_on_index ON public.igr_resolutions USING btree (due_on);
CREATE INDEX igr_resolutions_igr_forum_id_status_due_on_index ON public.igr_resolutions USING btree (igr_forum_id, status, due_on);
CREATE INDEX igr_resolutions_priority_index ON public.igr_resolutions USING btree (priority);
CREATE INDEX igr_resolutions_status_index ON public.igr_resolutions USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_resolutions');
    }
};
