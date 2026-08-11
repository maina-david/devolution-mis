<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_standards', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_standards_pkey');
            $table->uuid('assessment_thematic_area_id');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->text('norm_reference')->nullable();
            $table->decimal('weight', 7, 4);
            $table->smallInteger('sequence')->default(DB::raw('\'1\'::smallint'));
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_thematic_area_id', 'code'], 'assessment_standards_assessment_thematic_area_id_code_unique');
            $table->foreign(['assessment_thematic_area_id'], 'assessment_standards_assessment_thematic_area_id_foreign')
                ->references(['id'])
                ->on('assessment_thematic_areas')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_standards_name_index ON public.assessment_standards USING btree (name);
CREATE TRIGGER protect_assessment_standards_trigger BEFORE INSERT OR DELETE OR UPDATE ON assessment_standards FOR EACH ROW EXECUTE FUNCTION protect_assessment_scorecard_component();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_standards');
    }
};
