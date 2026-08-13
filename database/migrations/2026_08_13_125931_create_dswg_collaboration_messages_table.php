<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dswg_collaboration_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dswg_collaboration_thread_id')->constrained('dswg_collaboration_threads')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->char('checksum', 64)->unique();
            $table->timestampTz('posted_at', 0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['dswg_collaboration_thread_id', 'posted_at'], 'dswg_collaboration_messages_thread_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dswg_collaboration_messages');
    }
};
