<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\PartnerContribution;
use App\Models\PartnerContributionReconciliation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;

class ReconcilePartnerContribution
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerContribution $contribution, User $reviewer, array $attributes): PartnerContributionReconciliation
    {
        abort_unless($reviewer->can(ProgrammePermission::ManagePartners->value), 403);
        abort_if($contribution->reported_by === $reviewer->id, 403, __('partner-coordination.lifecycle.errors.contribution_reviewer_separation'));

        $reconciliation = DB::transaction(function () use ($contribution, $reviewer, $attributes): PartnerContributionReconciliation {
            $locked = PartnerContribution::query()->lockForUpdate()->findOrFail($contribution->id);
            $documents = $locked->documentLinks()->where('purpose', 'partner-contribution-reconciliation-evidence')->whereHas('document', fn ($query) => $query->where('scan_status', 'clean')->where('record_status', 'active'))->with('document:id,content_checksum')->get();
            abort_if($documents->isEmpty(), 422, __('partner-coordination.lifecycle.errors.clean_reconciliation_evidence_required'));
            $latestVersion = $locked->reconciliations()->max('version');
            $predecessorChecksum = $locked->reconciliations()->value('decision_checksum');
            $version = (is_numeric($latestVersion) ? (int) $latestVersion : 0) + 1;
            $reviewedAt = now();
            $committed = $this->money($attributes['verified_committed_amount']);
            $disbursed = $this->money($attributes['verified_disbursed_amount']);
            $inKind = $this->money($attributes['verified_in_kind_value']);
            $evidenceChecksum = $this->canonicalJson->checksum($documents->pluck('document.content_checksum')->sort()->values()->all());
            $snapshot = [
                'reported' => ['committed' => $locked->committed_amount, 'disbursed' => $locked->disbursed_amount, 'in_kind' => $locked->in_kind_value, 'currency' => $locked->currency, 'provenance' => $locked->provenance],
                'verified' => ['committed' => $committed, 'disbursed' => $disbursed, 'in_kind' => $inKind],
                'decision' => $attributes['decision'],
                'source_reference' => $attributes['source_reference'],
                'review_note' => $attributes['review_note'],
                'evidence_checksum' => $evidenceChecksum,
                'predecessor_checksum' => is_string($predecessorChecksum) ? $predecessorChecksum : null,
                'version' => $version,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt->toIso8601String(),
            ];

            return $locked->reconciliations()->create([
                'version' => $version,
                'decision' => $attributes['decision'],
                'verified_committed_amount' => $committed,
                'verified_disbursed_amount' => $disbursed,
                'verified_in_kind_value' => $inKind,
                'disbursement_variance' => $this->moneyFromCents($this->cents($disbursed) - $this->cents((string) $locked->disbursed_amount)),
                'source_reference' => $attributes['source_reference'],
                'review_note' => $attributes['review_note'],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => $reviewedAt,
                'evidence_checksum' => $evidenceChecksum,
                'predecessor_checksum' => is_string($predecessorChecksum) ? $predecessorChecksum : null,
                'decision_checksum' => $this->canonicalJson->checksum($snapshot),
                'snapshot' => $snapshot,
            ]);
        }, attempts: 3);

        $this->auditLogger->record($reviewer, $contribution, 'partner.contribution.reconciled', __('partner-coordination.lifecycle.audit.contribution_reconciled', ['version' => $reconciliation->version, 'decision' => $reconciliation->decision]), $contribution->project->lead_county_id, ['reconciliation_id' => $reconciliation->id, 'decision_checksum' => $reconciliation->decision_checksum, 'evidence_checksum' => $reconciliation->evidence_checksum]);

        return $reconciliation;
    }

    private function money(mixed $value): string
    {
        $raw = trim((string) $value);
        abort_unless(preg_match('/^\d+(?:\.\d{1,2})?$/', $raw) === 1, 422, __('partner-coordination.lifecycle.errors.money_precision'));
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = mb_substr(str_pad($fraction, 2, '0'), 0, 2);

        return ((int) $whole * 100) + ((int) $fraction * ($whole !== '' && $whole[0] === '-' ? -1 : 1));
    }

    private function moneyFromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
