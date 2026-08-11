<?php

namespace App\Console\Commands;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\PartnerAgreement;
use App\Models\PartnerContribution;
use App\Models\PartnerOperationalAlert;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('partners:monitor-operational-alerts')]
#[Description('Detect and notify idempotent partner agreement and contribution operational alerts')]
class MonitorPartnerOperationalAlerts extends Command
{
    public function handle(AuditLogger $auditLogger): int
    {
        $created = 0;
        $cutoff = today()->addDays((int) config('partners.agreement_expiry_notice_days'));
        $pendingCutoff = now()->subDays((int) config('partners.contribution_reconciliation_due_days'));

        PartnerAgreement::query()->whereIn('status', ['active', 'suspended'])->whereNotNull('ends_on')->whereDate('ends_on', '<=', $cutoff)->with(['partner.users', 'partner.counties'])->chunkById(100, function (Collection $agreements) use (&$created, $auditLogger): void {
            foreach ($agreements as $agreement) {
                $expired = $agreement->ends_on->isBefore(today());
                $type = $expired ? 'agreement_expired' : 'agreement_expiry_due';
                $severity = $expired ? 'critical' : 'warning';
                $county = $agreement->partner->counties->sortBy('code')->first();
                $summary = "{$agreement->reference} for {$agreement->partner->organization()->value('name')} ".($expired ? 'expired' : 'expires').' '.$agreement->ends_on->toFormattedDateString().'.';
                $created += $this->detect($agreement, $agreement->partner_profile_id, $county, $type, $severity, $summary, $agreement->ends_on->toDateString(), $agreement->partner->users, $auditLogger);
            }
        });

        PartnerContribution::query()->where('created_at', '<=', $pendingCutoff)->whereDoesntHave('reconciliations')->with(['partner.users', 'project.leadCounty'])->chunkById(100, function (Collection $contributions) use (&$created, $auditLogger): void {
            foreach ($contributions as $contribution) {
                $summary = "{$contribution->partner->organization()->value('name')} contribution for {$contribution->project->code} has not been reconciled within the control period.";
                $created += $this->detect($contribution, $contribution->partner_profile_id, $contribution->project->leadCounty, 'contribution_reconciliation_overdue', 'critical', $summary, null, $contribution->partner->users, $auditLogger);
            }
        });

        PartnerContribution::query()->whereHas('reconciliations', fn ($query) => $query->whereIn('decision', ['exception', 'rejected']))->with(['partner.users', 'project.leadCounty', 'reconciliations'])->chunkById(100, function (Collection $contributions) use (&$created, $auditLogger): void {
            foreach ($contributions as $contribution) {
                $latest = $contribution->reconciliations->first();
                if ($latest === null || ! in_array($latest->decision, ['exception', 'rejected'], true)) {
                    continue;
                }
                $summary = "{$contribution->partner->organization()->value('name')} contribution for {$contribution->project->code} has a {$latest->decision} reconciliation decision.";
                $created += $this->detect($contribution, $contribution->partner_profile_id, $contribution->project->leadCounty, 'contribution_reconciliation_'.$latest->decision, $latest->decision === 'rejected' ? 'critical' : 'warning', $summary, null, $contribution->partner->users, $auditLogger, $latest->decision_checksum);
            }
        });

        $this->resolveSupersededAlerts();
        $this->components->info("Created {$created} partner operational alert(s).");

        return self::SUCCESS;
    }

    /** @param Collection<int, User> $partnerUsers */
    private function detect(Model $subject, string $partnerId, ?County $county, string $type, string $severity, string $summary, ?string $dueOn, Collection $partnerUsers, AuditLogger $auditLogger, ?string $version = null): int
    {
        $fingerprint = hash('sha256', implode('|', [$subject->getMorphClass(), $subject->getKey(), $type, $version, $dueOn]));
        $alert = DB::transaction(function () use ($subject, $partnerId, $county, $type, $severity, $summary, $dueOn, $fingerprint): ?PartnerOperationalAlert {
            if (PartnerOperationalAlert::query()->where('fingerprint', $fingerprint)->exists()) {
                return null;
            }

            return PartnerOperationalAlert::create(['partner_profile_id' => $partnerId, 'county_id' => $county?->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'alert_type' => $type, 'severity' => $severity, 'fingerprint' => $fingerprint, 'summary' => $summary, 'due_on' => $dueOn, 'status' => 'open', 'detected_at' => now(), 'notified_at' => now()]);
        }, attempts: 3);
        if (! $alert instanceof PartnerOperationalAlert) {
            return 0;
        }

        $managers = User::permission(ProgrammePermission::ManagePartners->value)->get()->filter(fn (User $manager): bool => $county instanceof County ? $manager->canAccessCounty($county) : $manager->programmeRole()->hasNationalScope());
        $partnerUsers->merge($managers)->filter()->unique('id')->each(fn (User $recipient) => $recipient->notify(new ProgrammeAlert('Partner operational alert', $summary, 'partner-coordination')));
        $auditLogger->record(null, $subject, 'partner.operational_alert.detected', $summary, $county?->id, ['alert_id' => $alert->id, 'alert_type' => $type, 'severity' => $severity, 'fingerprint' => $fingerprint]);

        return 1;
    }

    private function resolveSupersededAlerts(): void
    {
        PartnerOperationalAlert::query()->where('status', 'open')->where('subject_type', (new PartnerAgreement)->getMorphClass())->with('subject')->get()->each(function (PartnerOperationalAlert $alert): void {
            $agreement = $alert->subject;
            if (! $agreement instanceof PartnerAgreement || ! in_array($agreement->status, ['active', 'suspended'], true) || $agreement->ends_on === null || ($alert->alert_type === 'agreement_expiry_due' && $agreement->ends_on->isPast())) {
                $alert->update(['status' => 'system_resolved', 'resolved_at' => now(), 'resolution' => 'The monitored agreement condition changed.']);
            }
        });
        PartnerOperationalAlert::query()->where('status', 'open')->where('subject_type', (new PartnerContribution)->getMorphClass())->with('subject.reconciliations')->get()->each(function (PartnerOperationalAlert $alert): void {
            $contribution = $alert->subject;
            $latest = $contribution instanceof PartnerContribution ? $contribution->reconciliations->first() : null;
            $superseded = $alert->alert_type === 'contribution_reconciliation_overdue'
                ? $latest !== null
                : $latest !== null && $latest->decision === 'verified';
            if (! $contribution instanceof PartnerContribution || $superseded) {
                $alert->update(['status' => 'system_resolved', 'resolved_at' => now(), 'resolution' => 'A later verified reconciliation superseded this condition.']);
            }
        });
    }
}
