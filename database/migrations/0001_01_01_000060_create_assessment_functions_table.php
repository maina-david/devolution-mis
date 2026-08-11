<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_functions', function (Blueprint $table): void {
            $table->uuid('id')->primary('assessment_functions_pkey');
            $table->uuid('assessment_scorecard_version_id');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('function_type', 255)->default('devolved');
            $table->decimal('weight', 7, 4);
            $table->smallInteger('sequence')->default(DB::raw('\'1\'::smallint'));
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_scorecard_version_id', 'code'], 'assessment_functions_assessment_scorecard_version_id_code_uniqu');
            $table->foreign(['assessment_scorecard_version_id'], 'assessment_functions_assessment_scorecard_version_id_foreign')
                ->references(['id'])
                ->on('assessment_scorecard_versions')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_functions_function_type_index ON public.assessment_functions USING btree (function_type);
CREATE INDEX assessment_functions_name_index ON public.assessment_functions USING btree (name);
CREATE TRIGGER protect_assessment_functions_trigger BEFORE INSERT OR DELETE OR UPDATE ON assessment_functions FOR EACH ROW EXECUTE FUNCTION protect_assessment_scorecard_component();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_functions');
    }
};
