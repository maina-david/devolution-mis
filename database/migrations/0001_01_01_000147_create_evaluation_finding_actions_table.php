<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_finding_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('evaluation_finding_id');
            $table->uuid('accountable_owner_id');
            $table->uuid('created_by');
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description');
            $table->string('success_indicator', 255);
            $table->string('target', 255);
            $table->date('due_at');
            $table->decimal('weight_percentage', 5, 2);
            $table->decimal('progress_percentage', 5, 2)->default(DB::raw('\'0\'::numeric'));
            $table->string('status', 255)->default('open');
            $table->string('checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['evaluation_finding_id', 'code'], 'evaluation_finding_actions_evaluation_finding_id_code_unique');
            $table->foreign(['accountable_owner_id'], 'evaluation_finding_actions_accountable_owner_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'evaluation_finding_actions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['evaluation_finding_id'], 'evaluation_finding_actions_evaluation_finding_id_foreign')
                ->references(['id'])
                ->on('evaluation_findings')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."evaluation_finding_actions" ADD CONSTRAINT "evaluation_finding_actions_progress_check" CHECK (progress_percentage >= 0::numeric AND progress_percentage <= 100::numeric);
ALTER TABLE public."evaluation_finding_actions" ADD CONSTRAINT "evaluation_finding_actions_weight_check" CHECK (weight_percentage > 0::numeric AND weight_percentage <= 100::numeric);
CREATE INDEX evaluation_finding_actions_accountable_owner_id_status_due_at_i ON public.evaluation_finding_actions USING btree (accountable_owner_id, status, due_at);
CREATE TRIGGER evaluation_finding_actions_completed_immutable BEFORE DELETE OR UPDATE ON evaluation_finding_actions FOR EACH ROW EXECUTE FUNCTION protect_completed_evaluation_finding_actions();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_finding_actions');
    }
};
