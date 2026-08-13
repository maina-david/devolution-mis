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
        Schema::create('learning_question_banks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learning_course_id');
            $table->string('code', 80);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('selection_count');
            $table->boolean('randomize_questions')->default(true);
            $table->boolean('randomize_options')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 20)->default('published');
            $table->char('checksum', 64);
            $table->uuid('created_by');
            $table->timestamp('published_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['learning_course_id', 'code', 'version']);
            $table->index(['learning_course_id', 'status']);
            $table->foreign('learning_course_id')->references('id')->on('learning_courses')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_published_learning_question_bank() RETURNS trigger AS $$
BEGIN
    IF OLD.status IN ('published', 'retired') THEN
        RAISE EXCEPTION 'Published learning question banks are immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER protect_published_learning_question_bank_trigger BEFORE UPDATE OR DELETE ON learning_question_banks FOR EACH ROW EXECUTE FUNCTION protect_published_learning_question_bank();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_question_banks');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_published_learning_question_bank();');
    }
};
