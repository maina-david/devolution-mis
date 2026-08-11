<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterion_evidence_requirements', function (Blueprint $table): void {
            $table->uuid('id')->primary('criterion_evidence_requirements_pkey');
            $table->uuid('assessment_criterion_id');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->smallInteger('minimum_documents')->default(DB::raw('\'1\'::smallint'));
            $table->jsonb('allowed_categories')->default(DB::raw('\'[]\'::jsonb'));
            $table->jsonb('accepted_mime_types')->default(DB::raw('\'[]\'::jsonb'));
            $table->boolean('requires_verification')->default(true);
            $table->boolean('is_mandatory')->default(true);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['assessment_criterion_id', 'code'], 'criterion_evidence_requirements_assessment_criterion_id_code_un');
            $table->foreign(['assessment_criterion_id'], 'criterion_evidence_requirements_assessment_criterion_id_foreign')
                ->references(['id'])
                ->on('assessment_criteria')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX criterion_evidence_requirements_is_mandatory_index ON public.criterion_evidence_requirements USING btree (is_mandatory);
CREATE TRIGGER protect_criterion_evidence_requirements_trigger BEFORE INSERT OR DELETE OR UPDATE ON criterion_evidence_requirements FOR EACH ROW EXECUTE FUNCTION protect_assessment_scorecard_component();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('criterion_evidence_requirements');
    }
};
