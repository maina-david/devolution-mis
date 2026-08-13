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
        Schema::create('learning_question_bank_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learning_question_bank_id');
            $table->uuid('learning_quiz_question_id');
            $table->string('variant_group', 100);
            $table->string('difficulty', 20)->default('standard');
            $table->jsonb('tags')->default(DB::raw("'[]'::jsonb"));
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['learning_question_bank_id', 'learning_quiz_question_id']);
            $table->index(['learning_question_bank_id', 'variant_group']);
            $table->index(['learning_question_bank_id', 'difficulty']);
            $table->foreign('learning_question_bank_id')->references('id')->on('learning_question_banks')->cascadeOnDelete();
            $table->foreign('learning_quiz_question_id')->references('id')->on('learning_quiz_questions')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION protect_learning_question_bank_item() RETURNS trigger AS $$
DECLARE bank_status text;
BEGIN
    SELECT status INTO bank_status FROM learning_question_banks WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.learning_question_bank_id ELSE NEW.learning_question_bank_id END;
    IF bank_status IN ('published', 'retired') THEN
        RAISE EXCEPTION 'Published learning question bank items are immutable';
    END IF;
    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER protect_learning_question_bank_item_trigger BEFORE INSERT OR UPDATE OR DELETE ON learning_question_bank_items FOR EACH ROW EXECUTE FUNCTION protect_learning_question_bank_item();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_question_bank_items');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_learning_question_bank_item();');
    }
};
