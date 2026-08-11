<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table): void {
            $table->uuid('id')->primary('performance_cycles_pkey');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('goal_setting_deadline');
            $table->date('midterm_review_deadline')->nullable();
            $table->date('final_review_deadline');
            $table->string('status', 255)->default('draft');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'performance_cycles_code_unique');
            $table->foreign(['created_by'], 'performance_cycles_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_cycles_status_index ON public.performance_cycles USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_cycles');
    }
};
