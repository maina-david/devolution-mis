<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary('performance_reviews_pkey');
            $table->uuid('performance_plan_id');
            $table->uuid('reviewer_id');
            $table->string('stage', 255);
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('comments');
            $table->text('capacity_gaps')->nullable();
            $table->text('development_actions')->nullable();
            $table->timestamp('reviewed_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['performance_plan_id'], 'performance_reviews_performance_plan_id_foreign')
                ->references(['id'])
                ->on('performance_plans')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['reviewer_id'], 'performance_reviews_reviewer_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX performance_reviews_performance_plan_id_stage_index ON public.performance_reviews USING btree (performance_plan_id, stage);
CREATE INDEX performance_reviews_stage_index ON public.performance_reviews USING btree (stage);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
