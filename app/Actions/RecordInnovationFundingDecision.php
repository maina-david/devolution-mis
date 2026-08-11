<?php

namespace App\Actions;

use App\Models\DevolutionInnovation;
use App\Models\InnovationFundingDecision;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInnovationFundingDecision
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(DevolutionInnovation $innovation, User $actor, array $attributes): InnovationFundingDecision
    {
        return DB::transaction(function () use ($innovation, $actor, $attributes): InnovationFundingDecision {
            $innovation = DevolutionInnovation::query()->lockForUpdate()->findOrFail($innovation->id);
            abort_unless($actor->canAccessCounty($innovation->county), 403);
            $this->guard($innovation, $actor, $attributes);
            $previous = $innovation->fundingDecisions()->latest('decision_version')->lockForUpdate()->first();
            $decidedAt = now();
            $evidence = [
                'innovation_id' => $innovation->id,
                'version' => $previous ? $previous->decision_version + 1 : 1,
                'decision' => $attributes['decision'],
                'amount' => number_format((float) $attributes['amount'], 2, '.', ''),
                'currency' => mb_strtoupper((string) $attributes['currency']),
                'funding_type' => $attributes['funding_type'],
                'reference' => $attributes['decision_reference'],
                'rationale' => $attributes['rationale'],
                'actor_id' => $actor->id,
                'decided_at' => $decidedAt->toIso8601String(),
                'previous_checksum' => $previous?->evidence_checksum,
            ];
            $decision = InnovationFundingDecision::create([
                'devolution_innovation_id' => $innovation->id,
                'decision_version' => $evidence['version'],
                'decision' => $attributes['decision'],
                'amount' => $attributes['amount'],
                'currency' => $evidence['currency'],
                'funding_type' => $attributes['funding_type'],
                'decision_reference' => $attributes['decision_reference'],
                'rationale' => $attributes['rationale'],
                'decided_by' => $actor->id,
                'decided_at' => $decidedAt,
                'previous_checksum' => $previous?->evidence_checksum,
                'evidence_checksum' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR)),
            ]);
            $this->auditLogger->record($actor, $decision, 'knowledge.innovation.funding-decided', "Funding decision v{$decision->decision_version} recorded for {$innovation->reference}.", $innovation->county_id, ['decision' => $decision->decision, 'amount' => $decision->amount, 'previous_checksum' => $decision->previous_checksum]);

            return $decision->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function guard(DevolutionInnovation $innovation, User $actor, array $attributes): void
    {
        if ($innovation->status !== 'incubating') {
            throw ValidationException::withMessages(['innovation' => 'Funding decisions may only be recorded during incubation.']);
        }
        if ($innovation->submitted_by === $actor->id || $innovation->panelReviews()->where('reviewer_id', $actor->id)->exists()) {
            throw ValidationException::withMessages(['decision' => 'The submitter and screening panel members cannot make the funding decision.']);
        }
        $approved = $attributes['decision'] === 'approved';
        if ($approved && ((float) $attributes['amount'] <= 0 || $attributes['funding_type'] === 'not_applicable')) {
            throw ValidationException::withMessages(['amount' => 'Approved funding requires a positive amount and applicable funding type.']);
        }
        if (! $approved && ((float) $attributes['amount'] !== 0.0 || $attributes['funding_type'] !== 'not_applicable')) {
            throw ValidationException::withMessages(['amount' => 'Declined or unnecessary funding must use zero amount and not-applicable funding type.']);
        }
    }
}
