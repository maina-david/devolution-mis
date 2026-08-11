<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_cohort_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('learning_cohort_id');
            $table->uuid('learning_enrollment_id');
            $table->uuid('added_by');
            $table->timestampTz('joined_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['learning_cohort_id', 'learning_enrollment_id'], 'learning_cohort_enrollment_unique');
            $table->foreign(['added_by'], 'learning_cohort_memberships_added_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_cohort_id'], 'learning_cohort_memberships_learning_cohort_id_foreign')
                ->references(['id'])
                ->on('learning_cohorts')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_enrollment_id'], 'learning_cohort_memberships_learning_enrollment_id_foreign')
                ->references(['id'])
                ->on('learning_enrollments')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_cohort_memberships');
    }
};
