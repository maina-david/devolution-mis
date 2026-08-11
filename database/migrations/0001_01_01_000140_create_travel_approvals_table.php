<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('travel_request_id');
            $table->uuid('actor_id');
            $table->string('stage', 255);
            $table->string('decision', 255);
            $table->text('rationale');
            $table->decimal('approved_cost', 18, 2)->nullable();
            $table->string('source_system', 255)->default('idmis');
            $table->string('external_reference', 255)->nullable();
            $table->timestamp('decided_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['actor_id'], 'travel_approvals_actor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['travel_request_id'], 'travel_approvals_travel_request_id_foreign')
                ->references(['id'])
                ->on('travel_requests')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX travel_approvals_decision_index ON public.travel_approvals USING btree (decision);
CREATE INDEX travel_approvals_stage_index ON public.travel_approvals USING btree (stage);
CREATE INDEX travel_approvals_travel_request_id_stage_decision_index ON public.travel_approvals USING btree (travel_request_id, stage, decision);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_approvals');
    }
};
