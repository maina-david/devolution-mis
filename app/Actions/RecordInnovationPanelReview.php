<?php

namespace App\Actions;

use App\Models\DevolutionInnovation;
use App\Models\InnovationPanelReview;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInnovationPanelReview
{
    private const RUBRIC = [
        'code' => 'IDMIS-INNOVATION-RUBRIC-v1',
        'weights' => ['strategic_fit' => 0.30, 'feasibility' => 0.25, 'inclusion' => 0.20, 'evidence' => 0.25],
    ];

    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionInnovation $innovation, User $actor, array $attributes): InnovationPanelReview
    {
        return DB::transaction(function () use ($innovation, $actor, $attributes): InnovationPanelReview {
            $innovation = DevolutionInnovation::query()->lockForUpdate()->findOrFail($innovation->id);
            abort_unless($actor->canAccessCounty($innovation->county), 403);
            $this->guard($innovation, $actor);

            $weights = self::RUBRIC['weights'];
            $weightedScore = round(
                ((float) $attributes['strategic_fit_score'] * $weights['strategic_fit'])
                + ((float) $attributes['feasibility_score'] * $weights['feasibility'])
                + ((float) $attributes['inclusion_score'] * $weights['inclusion'])
                + ((float) $attributes['evidence_score'] * $weights['evidence']),
                2,
            );
            $rubricChecksum = hash('sha256', json_encode(self::RUBRIC, JSON_THROW_ON_ERROR));
            $reviewedAt = now();
            $evidence = [
                'innovation_id' => $innovation->id,
                'reviewer_id' => $actor->id,
                'rubric_checksum' => $rubricChecksum,
                'scores' => [
                    'strategic_fit' => (float) $attributes['strategic_fit_score'],
                    'feasibility' => (float) $attributes['feasibility_score'],
                    'inclusion' => (float) $attributes['inclusion_score'],
                    'evidence' => (float) $attributes['evidence_score'],
                ],
                'weighted_score' => $weightedScore,
                'recommendation' => $attributes['recommendation'],
                'rationale' => $attributes['rationale'],
                'reviewed_at' => $reviewedAt->toIso8601String(),
            ];
            $review = InnovationPanelReview::create([
                'devolution_innovation_id' => $innovation->id,
                'reviewer_id' => $actor->id,
                'rubric_code' => self::RUBRIC['code'],
                'rubric_checksum' => $rubricChecksum,
                'strategic_fit_score' => $attributes['strategic_fit_score'],
                'feasibility_score' => $attributes['feasibility_score'],
                'inclusion_score' => $attributes['inclusion_score'],
                'evidence_score' => $attributes['evidence_score'],
                'weighted_score' => $weightedScore,
                'recommendation' => $attributes['recommendation'],
                'rationale' => $attributes['rationale'],
                'reviewed_at' => $reviewedAt,
                'evidence_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
            ]);
            $this->auditLogger->record($actor, $review, 'knowledge.innovation.panel-reviewed', "Panel review recorded for {$innovation->reference}.", $innovation->county_id, ['weighted_score' => $weightedScore, 'recommendation' => $attributes['recommendation'], 'rubric_checksum' => $rubricChecksum]);

            return $review->refresh();
        });
    }

    private function guard(DevolutionInnovation $innovation, User $actor): void
    {
        if ($innovation->status !== 'screening') {
            throw ValidationException::withMessages(['innovation' => 'Panel reviews may only be recorded during screening.']);
        }
        if ($innovation->submitted_by === $actor->id) {
            throw ValidationException::withMessages(['reviewer' => 'The innovation submitter cannot serve on its screening panel.']);
        }
        if ($innovation->panelReviews()->where('reviewer_id', $actor->id)->exists()) {
            throw ValidationException::withMessages(['reviewer' => 'This reviewer has already submitted an immutable panel review.']);
        }
    }
}
