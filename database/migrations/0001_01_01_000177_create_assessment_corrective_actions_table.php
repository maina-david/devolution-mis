<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_corrective_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_corrective_actions_pkey');
            $table->uuid('assessment_corrective_plan_id');
            $table->uuid('accountable_owner_id');
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description');
            $table->string('success_indicator', 255);
            $table->string('target', 255);
            $table->date('due_at');
            $table->decimal('progress_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('status', 30)->default('planned');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_corrective_plan_id', 'code'], 'assessment_corrective_actions_assessment_corrective_plan_id_cod');
            $table->foreign(['accountable_owner_id'], 'assessment_corrective_actions_accountable_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['assessment_corrective_plan_id'], 'assessment_corrective_actions_assessment_corrective_plan_id_for')
                ->references(['id'])
                ->on('assessment_corrective_plans')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_corrective_actions_accountable_owner_id_status_due_a ON public.assessment_corrective_actions USING btree (accountable_owner_id, status, due_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_corrective_actions');
    }
};
