<?php

namespace App\Console\Commands;

use App\Models\AccessDelegation;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\DelegatedAccessResolver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('security:reconcile-delegated-access')]
#[Description('Activate scheduled access and expire time-bound or emergency grants')]
class ReconcileAccessDelegations extends Command
{
    public function handle(AuditLogger $auditLogger, DelegatedAccessResolver $delegatedAccess): int
    {
        $changed = 0;
        AccessDelegation::query()->where('status', 'scheduled')->where('starts_at', '<=', now())->where('expires_at', '>', now())->chunkById(100, function ($delegations) use (&$changed, $auditLogger, $delegatedAccess): void {
            foreach ($delegations as $delegation) {
                DB::transaction(function () use ($delegation, &$changed, $auditLogger, $delegatedAccess): void {
                    $locked = AccessDelegation::query()->lockForUpdate()->whereKey($delegation->id)->sole();
                    if ($locked->status !== 'scheduled' || $locked->starts_at->isFuture() || ! $locked->expires_at->isFuture()) {
                        return;
                    }
                    $locked->update(['status' => 'active', 'activated_at' => now()]);
                    $delegatedAccess->forget($locked->beneficiary_id);
                    $locked->load('beneficiary');
                    $locked->beneficiary->notify(new ProgrammeAlert('Temporary access activated', "{$locked->reference} is active until {$locked->expires_at->toDayDateTimeString()}.", 'security-governance'));
                    $auditLogger->record(null, $locked, 'security.delegation.activated', "Temporary access {$locked->reference} activated by schedule.");
                    $changed++;
                });
            }
        });

        AccessDelegation::query()->whereIn('status', ['scheduled', 'active'])->where('expires_at', '<=', now())->chunkById(100, function ($delegations) use (&$changed, $auditLogger, $delegatedAccess): void {
            foreach ($delegations as $delegation) {
                DB::transaction(function () use ($delegation, &$changed, $auditLogger, $delegatedAccess): void {
                    $locked = AccessDelegation::query()->lockForUpdate()->whereKey($delegation->id)->sole();
                    if (! in_array($locked->status, ['scheduled', 'active'], true) || $locked->expires_at->isFuture()) {
                        return;
                    }
                    $status = $locked->access_type === 'emergency' ? 'review_pending' : 'expired';
                    $locked->update(['status' => $status, 'expired_at' => now()]);
                    $delegatedAccess->forget($locked->beneficiary_id);
                    $locked->load('beneficiary');
                    $locked->beneficiary->notify(new ProgrammeAlert($status === 'review_pending' ? 'Emergency access expired — review required' : 'Temporary access expired', "{$locked->reference} is no longer active.", 'security-governance'));
                    $auditLogger->record(null, $locked, 'security.delegation.expired', "Temporary access {$locked->reference} expired.", metadata: ['post_use_review_required' => $status === 'review_pending']);
                    $changed++;
                });
            }
        });

        $this->components->info("Reconciled {$changed} delegated-access grant(s).");

        return self::SUCCESS;
    }
}
