<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_thematic_areas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assessment_function_id');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('weight', 7, 4);
            $table->smallInteger('sequence')->default(DB::raw('\'1\'::smallint'));
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_function_id', 'code'], 'assessment_thematic_areas_assessment_function_id_code_unique');
            $table->foreign(['assessment_function_id'], 'assessment_thematic_areas_assessment_function_id_foreign')
                ->references(['id'])
                ->on('assessment_functions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_thematic_areas_name_index ON public.assessment_thematic_areas USING btree (name);
CREATE TRIGGER protect_assessment_thematic_areas_trigger BEFORE INSERT OR DELETE OR UPDATE ON assessment_thematic_areas FOR EACH ROW EXECUTE FUNCTION protect_assessment_scorecard_component();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_thematic_areas');
    }
};
