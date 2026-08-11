<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IntegrationContract;
use App\Models\IntegrationExchange;
use App\Models\PartnerContribution;
use App\Models\PartnerContributionSourceMatch;
use App\Models\ReconciliationException;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IntegrationPayloadValidator;
use App\Support\CanonicalJson;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type MoneySnapshot array{committed: numeric-string, disbursed: numeric-string, in_kind: numeric-string, currency: string}
 * @phpstan-type Comparison array{outcome: string, contribution: PartnerContribution|null, county_id: string|null, external_reference: string|null, source_disbursed_amount: numeric-string|null, amounts: array{source_committed_amount: numeric-string|null, source_disbursed_amount: numeric-string|null, source_in_kind_value: numeric-string|null, local_committed_amount: numeric-string|null, local_disbursed_amount: numeric-string|null, local_in_kind_value: numeric-string|null, disbursement_variance: numeric-string|null, source_currency: string|null, local_currency: string|null}, snapshot: array<string, mixed>}
 */
class ReconcilePartnerContributionExchanges
{
    public const ResourceName = 'partner_contribution_statement';

    public function __construct(
        private CanonicalJson $canonicalJson,
        private IntegrationPayloadValidator $payloadValidator,
        private AuditLogger $auditLogger,
    ) {}

    public function handle(IntegrationContract $contract, User $actor, CarbonInterface $periodFrom, CarbonInterface $periodTo): ?ReconciliationRun
    {
        abort_unless($actor->can(ProgrammePermission::ManageIntegrations->value), 403);
        abort_if($periodTo->isBefore($periodFrom), 422, 'The reconciliation period is invalid.');
        $contract->loadMissing('system');
        abort_unless($contract->resource_name === self::ResourceName && $contract->status === 'published', 409, 'A published partner-contribution interface contract is required.');
        abort_if($contract->effective_from?->isAfter(now()) || $contract->effective_to?->isBefore(now()), 409, 'The partner-contribution interface contract is not currently effective.');

        $exchangeIds = $this->unprocessedExchanges($contract, $periodFrom, $periodTo)->pluck('id');
        if ($exchangeIds->isEmpty()) {
            return null;
        }

        $run = DB::transaction(function () use ($contract, $actor, $periodFrom, $periodTo, $exchangeIds): ?ReconciliationRun {
            $exchanges = IntegrationExchange::query()
                ->whereKey($exchangeIds)
                ->whereDoesntHave('partnerContributionSourceMatch')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($exchanges->isEmpty()) {
                return null;
            }

            $batchChecksum = $this->canonicalJson->checksum($exchanges->map(fn (IntegrationExchange $exchange): array => ['id' => $exchange->id, 'checksum' => $exchange->payload_checksum])->all());
            $run = ReconciliationRun::create([
                'integration_system_id' => $contract->integration_system_id,
                'integration_contract_id' => $contract->id,
                'initiated_by' => $actor->id,
                'reference' => 'PCR-'.now()->format('YmdHis').'-'.mb_strtoupper(mb_substr($batchChecksum, 0, 10)),
                'period_from' => $periodFrom->toDateString(),
                'period_to' => $periodTo->toDateString(),
                'status' => 'running',
                'started_at' => now(),
                'metadata' => ['resource' => self::ResourceName, 'contract_checksum' => $contract->content_checksum, 'batch_checksum' => $batchChecksum],
            ]);

            $matchedCount = 0;
            $sourceTotal = '0.00';
            $targetTotal = '0.00';
            $targetIds = [];
            $matchChecksums = [];
            foreach ($exchanges as $exchange) {
                $comparison = $this->compare($contract, $exchange, $actor);
                $snapshot = [...$comparison['snapshot'], 'run_id' => $run->id, 'exchange_id' => $exchange->id, 'source_checksum' => $exchange->payload_checksum, 'matched_by' => ['id' => $actor->id, 'name' => $actor->name], 'matched_at' => now()->toIso8601String()];
                $matchChecksum = $this->canonicalJson->checksum($snapshot);
                $match = PartnerContributionSourceMatch::create([
                    'reconciliation_run_id' => $run->id,
                    'integration_exchange_id' => $exchange->id,
                    'partner_contribution_id' => $comparison['contribution']?->id,
                    'county_id' => $comparison['county_id'],
                    'matched_by' => $actor->id,
                    'matched_by_name' => $actor->name,
                    'external_reference' => $comparison['external_reference'],
                    'local_reference' => $comparison['contribution']?->id,
                    'outcome' => $comparison['outcome'],
                    ...$comparison['amounts'],
                    'source_checksum' => $exchange->payload_checksum,
                    'match_checksum' => $matchChecksum,
                    'snapshot' => $snapshot,
                    'matched_at' => $snapshot['matched_at'],
                ]);
                $matchChecksums[] = $matchChecksum;
                if ($comparison['source_disbursed_amount'] !== null) {
                    $sourceTotal = bcadd($sourceTotal, $comparison['source_disbursed_amount'], 2);
                }
                if ($comparison['contribution'] instanceof PartnerContribution) {
                    $targetIds[$comparison['contribution']->id] = true;
                    $targetTotal = bcadd($targetTotal, (string) $comparison['contribution']->disbursed_amount, 2);
                }
                if ($comparison['outcome'] === 'matched') {
                    $matchedCount++;
                } else {
                    $this->openException($run, $exchange, $match, $comparison);
                }
            }

            $exceptionCount = $exchanges->count() - $matchedCount;
            $resultSnapshot = ['run_id' => $run->id, 'batch_checksum' => $batchChecksum, 'match_checksums' => $matchChecksums, 'source_count' => $exchanges->count(), 'target_count' => count($targetIds), 'matched_count' => $matchedCount, 'exception_count' => $exceptionCount, 'source_total' => $sourceTotal, 'target_total' => $targetTotal];
            $run->update([
                'source_count' => $resultSnapshot['source_count'],
                'target_count' => $resultSnapshot['target_count'],
                'matched_count' => $resultSnapshot['matched_count'],
                'exception_count' => $resultSnapshot['exception_count'],
                'source_total' => $resultSnapshot['source_total'],
                'target_total' => $resultSnapshot['target_total'],
                'status' => $exceptionCount > 0 ? 'exceptions' : 'reconciled',
                'result_checksum' => $this->canonicalJson->checksum($resultSnapshot),
                'completed_at' => now(),
                'metadata' => array_merge($run->metadata ?? [], ['batch_checksum' => $batchChecksum, 'match_checksums' => $matchChecksums]),
            ]);

            return $run->refresh();
        }, attempts: 3);

        if ($run instanceof ReconciliationRun) {
            $this->auditLogger->record($actor, $run, 'partner.contribution.exchange_reconciled', "Partner contribution source run {$run->reference} completed with {$run->exception_count} exception(s).", metadata: ['contract_id' => $contract->id, 'result_checksum' => $run->result_checksum, 'source_count' => $run->source_count, 'matched_count' => $run->matched_count]);
        }

        return $run;
    }

    /** @return Builder<IntegrationExchange> */
    private function unprocessedExchanges(IntegrationContract $contract, CarbonInterface $periodFrom, CarbonInterface $periodTo): Builder
    {
        return IntegrationExchange::query()
            ->whereBelongsTo($contract, 'contract')
            ->where('direction', 'inbound')
            ->where('status', 'succeeded')
            ->where(function (Builder $query) use ($periodFrom, $periodTo): void {
                $query->whereBetween('source_occurred_at', [$periodFrom->copy()->startOfDay(), $periodTo->copy()->endOfDay()])
                    ->orWhere(fn (Builder $query) => $query->whereNull('source_occurred_at')->whereBetween('accepted_at', [$periodFrom->copy()->startOfDay(), $periodTo->copy()->endOfDay()]));
            })
            ->whereDoesntHave('partnerContributionSourceMatch')
            ->orderBy('id');
    }

    /**
     * @return Comparison
     */
    private function compare(IntegrationContract $contract, IntegrationExchange $exchange, User $actor): array
    {
        $payload = $exchange->request_payload;
        try {
            $this->payloadValidator->validate($payload, $contract->request_schema);
            $source = [
                'committed' => $this->money($payload['committed_amount'] ?? null),
                'disbursed' => $this->money($payload['disbursed_amount'] ?? null),
                'in_kind' => $this->money($payload['in_kind_value'] ?? null),
                'currency' => mb_strtoupper((string) ($payload['currency'] ?? '')),
            ];
        } catch (ValidationException) {
            return $this->comparison('invalid_payload', null, $exchange, null, ['payload_keys' => array_keys($payload)]);
        }

        $contributionId = $payload['partner_contribution_id'] ?? null;
        $contribution = is_string($contributionId) ? PartnerContribution::query()->with('project:id,lead_county_id')->find($contributionId) : null;
        if (! $contribution instanceof PartnerContribution) {
            return $this->comparison('missing_target', null, $exchange, $source, ['partner_contribution_id' => $contributionId]);
        }

        $countyId = $contribution->project->lead_county_id;
        $outcome = match (true) {
            $exchange->created_by === $actor->id => 'control_conflict',
            $exchange->county_id !== null && $exchange->county_id !== $countyId => 'county_scope_mismatch',
            $source['currency'] !== mb_strtoupper($contribution->currency) => 'currency_mismatch',
            bccomp($source['committed'], (string) $contribution->committed_amount, 2) !== 0,
            bccomp($source['disbursed'], (string) $contribution->disbursed_amount, 2) !== 0,
            bccomp($source['in_kind'], (string) $contribution->in_kind_value, 2) !== 0 => 'value_mismatch',
            default => 'matched',
        };

        return $this->comparison($outcome, $contribution, $exchange, $source);
    }

    /**
     * @param  MoneySnapshot|null  $source
     * @param  array<string, mixed>  $context
     * @return Comparison
     */
    private function comparison(string $outcome, ?PartnerContribution $contribution, IntegrationExchange $exchange, ?array $source, array $context = []): array
    {
        $local = $contribution ? ['committed' => (string) $contribution->committed_amount, 'disbursed' => (string) $contribution->disbursed_amount, 'in_kind' => (string) $contribution->in_kind_value, 'currency' => $contribution->currency] : null;

        return [
            'outcome' => $outcome,
            'contribution' => $contribution,
            'county_id' => $contribution?->project->lead_county_id ?? $exchange->county_id,
            'external_reference' => $exchange->external_reference,
            'source_disbursed_amount' => $source['disbursed'] ?? null,
            'amounts' => [
                'source_committed_amount' => $source['committed'] ?? null,
                'source_disbursed_amount' => $source['disbursed'] ?? null,
                'source_in_kind_value' => $source['in_kind'] ?? null,
                'local_committed_amount' => $local['committed'] ?? null,
                'local_disbursed_amount' => $local['disbursed'] ?? null,
                'local_in_kind_value' => $local['in_kind'] ?? null,
                'disbursement_variance' => $source && $local ? bcsub($source['disbursed'], $local['disbursed'], 2) : null,
                'source_currency' => $source['currency'] ?? null,
                'local_currency' => $local['currency'] ?? null,
            ],
            'snapshot' => ['outcome' => $outcome, 'external_reference' => $exchange->external_reference, 'source' => $source, 'local' => $local, 'context' => $context],
        ];
    }

    /** @param array<string, mixed> $comparison */
    private function openException(ReconciliationRun $run, IntegrationExchange $exchange, PartnerContributionSourceMatch $match, array $comparison): void
    {
        ReconciliationException::create([
            'reconciliation_run_id' => $run->id,
            'integration_exchange_id' => $exchange->id,
            'county_id' => $match->county_id,
            'external_reference' => $match->external_reference,
            'local_reference' => $match->local_reference,
            'exception_type' => $match->outcome,
            'field_name' => in_array($match->outcome, ['value_mismatch', 'currency_mismatch'], true) ? 'amounts' : null,
            'severity' => in_array($match->outcome, ['control_conflict', 'county_scope_mismatch'], true) ? 'critical' : 'high',
            'expected_value' => $this->canonicalJson->encode($comparison['snapshot']['local'] ?? null),
            'actual_value' => $this->canonicalJson->encode($comparison['snapshot']['source'] ?? $comparison['snapshot']['context']),
            'description' => "Partner contribution source comparison produced {$match->outcome}; human review and clean DMS evidence are required before a reconciliation decision.",
            'status' => 'open',
        ]);
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        $raw = trim((string) $value);
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $raw) !== 1) {
            throw ValidationException::withMessages(['payload.amounts' => 'Partner contribution amounts must be non-negative with no more than two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $normalized = (ltrim($whole, '0') ?: '0').'.'.str_pad($fraction, 2, '0');
        if (! is_numeric($normalized)) {
            throw ValidationException::withMessages(['payload.amounts' => 'Partner contribution amounts could not be normalized.']);
        }

        return $normalized;
    }
}
