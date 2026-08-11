<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_criteria', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_criteria_pkey');
            $table->uuid('assessment_standard_id');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('weight', 7, 4);
            $table->decimal('maximum_score', 10, 4)->default(DB::raw('\'100\'::numeric'));
            $table->string('scoring_method', 255)->default('scale');
            $table->jsonb('formula')->default(DB::raw('\'{}\'::jsonb'));
            $table->jsonb('thresholds')->default(DB::raw('\'[]\'::jsonb'));
            $table->boolean('is_mandatory')->default(true);
            $table->smallInteger('sequence')->default(DB::raw('\'1\'::smallint'));
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_standard_id', 'code'], 'assessment_criteria_assessment_standard_id_code_unique');
            $table->foreign(['assessment_standard_id'], 'assessment_criteria_assessment_standard_id_foreign')
                ->references(['id'])
                ->on('assessment_standards')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_criteria_is_mandatory_index ON public.assessment_criteria USING btree (is_mandatory);
CREATE INDEX assessment_criteria_name_index ON public.assessment_criteria USING btree (name);
CREATE INDEX assessment_criteria_scoring_method_index ON public.assessment_criteria USING btree (scoring_method);
CREATE TRIGGER protect_assessment_criteria_trigger BEFORE INSERT OR DELETE OR UPDATE ON assessment_criteria FOR EACH ROW EXECUTE FUNCTION protect_assessment_scorecard_component();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_criteria');
    }
};
