<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goals', function (Blueprint $table): void {
            $table->uuid('id')->primary('performance_goals_pkey');
            $table->uuid('performance_plan_id');
            $table->string('code', 255);
            $table->string('title', 255);
            $table->text('description');
            $table->string('kpi', 255);
            $table->string('unit_of_measure', 255);
            $table->decimal('baseline_value', 18, 4)->nullable();
            $table->decimal('target_value', 18, 4);
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('weight', 5, 2);
            $table->decimal('self_rating', 5, 2)->nullable();
            $table->decimal('supervisor_rating', 5, 2)->nullable();
            $table->text('employee_narrative')->nullable();
            $table->text('supervisor_comment')->nullable();
            $table->string('evidence_reference', 255)->nullable();
            $table->smallInteger('sequence');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['performance_plan_id', 'code'], 'performance_goals_performance_plan_id_code_unique');
            $table->foreign(['performance_plan_id'], 'performance_goals_performance_plan_id_foreign')
                ->references(['id'])
                ->on('performance_plans')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goals');
    }
};
