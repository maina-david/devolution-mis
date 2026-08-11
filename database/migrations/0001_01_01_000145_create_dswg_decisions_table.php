<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary('dswg_decisions_pkey');
            $table->uuid('dswg_meeting_id');
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('decision_text');
            $table->string('decision_type', 255)->default('resolution');
            $table->string('status', 255)->default('adopted');
            $table->timestampTz('decided_at', 0);
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'dswg_decisions_code_unique');
            $table->foreign(['created_by'], 'dswg_decisions_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['dswg_meeting_id'], 'dswg_decisions_dswg_meeting_id_foreign')
                ->references(['id'])
                ->on('dswg_meetings')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX dswg_decisions_decision_type_index ON public.dswg_decisions USING btree (decision_type);
CREATE INDEX dswg_decisions_status_index ON public.dswg_decisions USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_decisions');
    }
};
