<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('innovation_panel_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('devolution_innovation_id');
            $table->uuid('reviewer_id');
            $table->string('rubric_code', 255);
            $table->char('rubric_checksum', 64);
            $table->decimal('strategic_fit_score', 5, 2);
            $table->decimal('feasibility_score', 5, 2);
            $table->decimal('inclusion_score', 5, 2);
            $table->decimal('evidence_score', 5, 2);
            $table->decimal('weighted_score', 5, 2);
            $table->string('recommendation', 255);
            $table->text('rationale');
            $table->timestamp('reviewed_at', 0);
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['devolution_innovation_id', 'reviewer_id'], 'innovation_panel_reviewer_unique');
            $table->foreign(['devolution_innovation_id'], 'innovation_panel_reviews_devolution_innovation_id_foreign')
                ->references(['id'])
                ->on('devolution_innovations')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['reviewer_id'], 'innovation_panel_reviews_reviewer_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
ALTER TABLE public."innovation_panel_reviews" ADD CONSTRAINT "innovation_panel_recommendation_check" CHECK (recommendation::text = ANY (ARRAY['advance'::character varying::text, 'revise'::character varying::text, 'reject'::character varying::text]));
ALTER TABLE public."innovation_panel_reviews" ADD CONSTRAINT "innovation_panel_scores_check" CHECK (strategic_fit_score >= 0::numeric AND strategic_fit_score <= 100::numeric AND feasibility_score >= 0::numeric AND feasibility_score <= 100::numeric AND inclusion_score >= 0::numeric AND inclusion_score <= 100::numeric AND evidence_score >= 0::numeric AND evidence_score <= 100::numeric AND weighted_score >= 0::numeric AND weighted_score <= 100::numeric);
CREATE TRIGGER innovation_panel_reviews_immutable BEFORE DELETE OR UPDATE ON innovation_panel_reviews FOR EACH ROW EXECUTE FUNCTION reject_innovation_panel_review_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('innovation_panel_reviews');
    }
};
