<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dswg_collaboration_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dswg_working_group_id')->constrained('dswg_working_groups')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('topic');
            $table->string('status', 24)->default('open');
            $table->timestampTz('last_activity_at', 0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['dswg_working_group_id', 'status', 'last_activity_at'], 'dswg_collaboration_threads_scope_index');
        });

        DB::statement("ALTER TABLE dswg_collaboration_threads ADD CONSTRAINT dswg_collaboration_threads_status_check CHECK (status IN ('open', 'closed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dswg_collaboration_threads');
    }
};
