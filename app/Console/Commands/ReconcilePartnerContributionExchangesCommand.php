<?php

namespace App\Console\Commands;

use App\Actions\ReconcilePartnerContributionExchanges;
use App\Enums\ProgrammePermission;
use App\Models\IntegrationContract;
use App\Models\ReconciliationRun;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('partners:reconcile-contribution-exchanges')]
#[Description('Match inbound partner contribution statements under effective published interface contracts')]
class ReconcilePartnerContributionExchangesCommand extends Command
{
    public function handle(ReconcilePartnerContributionExchanges $reconcile): int
    {
        $serviceEmail = (string) config('partners.reconciliation_service_user_email');
        if ($serviceEmail === '') {
            $this->components->warn(__('partner-coordination.command.reconciliation_inactive'));

            return self::SUCCESS;
        }
        $actor = User::query()->where('email', $serviceEmail)->first();
        if (! $actor instanceof User || ! $actor->can(ProgrammePermission::ManageIntegrations->value)) {
            $this->components->error(__('partner-coordination.command.service_identity_unauthorized'));

            return self::FAILURE;
        }

        $runs = 0;
        IntegrationContract::query()
            ->where('resource_name', ReconcilePartnerContributionExchanges::ResourceName)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->with('system')
            ->each(function (IntegrationContract $contract) use ($reconcile, $actor, &$runs): void {
                if ($reconcile->handle($contract, $actor, today()->subDays((int) config('partners.contribution_exchange_lookback_days')), today()) instanceof ReconciliationRun) {
                    $runs++;
                }
            });
        $this->components->info(trans_choice('partner-coordination.command.reconciliation_completed', $runs, ['count' => $runs]));

        return self::SUCCESS;
    }
}
