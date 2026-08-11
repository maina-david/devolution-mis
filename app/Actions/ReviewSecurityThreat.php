<?php

namespace App\Actions;

use App\Models\SecurityThreat;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReviewSecurityThreat
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{decision: string, treatment_status: string, residual_likelihood: int, residual_impact: int, risk_acceptance_reference?: string|null, review_note: string, evidence_references?: string|null} $attributes */
    public function handle(SecurityThreat $threat, User $reviewer, array $attributes): SecurityThreat
    {
        return DB::transaction(function () use ($threat, $reviewer, $attributes): SecurityThreat {
            $threat = SecurityThreat::query()->lockForUpdate()->findOrFail($threat->id);
            abort_unless($threat->status === 'submitted', 409, 'Only submitted threats can be reviewed.');
            abort_if($threat->submitted_by === $reviewer->id, 403, 'The threat author cannot independently review it.');
            $residualScore = $attributes['residual_likelihood'] * $attributes['residual_impact'];
            abort_if($attributes['decision'] === 'accepted' && $residualScore > $threat->inherent_risk_score, 409, 'Residual risk cannot exceed inherent risk without reassessment.');

            $threat->update(['reviewed_by' => $reviewer->id, 'status' => $attributes['decision'], 'treatment_status' => $attributes['treatment_status'], 'residual_likelihood' => $attributes['residual_likelihood'], 'residual_impact' => $attributes['residual_impact'], 'residual_risk_score' => $residualScore, 'risk_acceptance_reference' => $attributes['risk_acceptance_reference'] ?? null, 'reviewed_at' => now(), 'treatment_plan' => trim($threat->treatment_plan."\n\nIndependent review: ".$attributes['review_note']), 'evidence_references' => $this->csv($attributes['evidence_references'] ?? null)]);
            $this->auditLogger->record($reviewer, $threat, 'security.threat.reviewed', "Threat {$threat->reference} {$attributes['decision']} with residual score {$residualScore}.", metadata: ['decision' => $attributes['decision'], 'residual_score' => $residualScore]);

            return $threat->refresh();
        });
    }

    /** @return list<string> */
    private function csv(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map(trim(...), explode(',', $value ?? '')), fn (string $item): bool => $item !== '')));
    }
}
