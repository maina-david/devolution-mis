<?php

namespace App\Console\Commands;

use App\Actions\AttemptIntegrationExchangeDelivery;
use App\Enums\ProgrammePermission;
use App\Models\IntegrationExchange;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('integrations:retry-exchanges {--limit=250 : Maximum due exchanges to attempt}')]
#[Description('Retry due outbound integration exchanges under their published contract backoff policy.')]
class RetryIntegrationExchanges extends Command
{
    public function handle(AttemptIntegrationExchangeDelivery $delivery): int
    {
        $email = config('integrations.retry_service_user_email');
        if (! is_string($email) || $email === '') {
            $this->components->warn('Integration retry service identity is not configured; no retries were attempted.');

            return self::SUCCESS;
        }

        $actor = User::query()->where('email', $email)->first();
        if (! $actor instanceof User || ! $actor->can(ProgrammePermission::ManageIntegrations->value)) {
            $this->components->error('The configured integration retry service identity is missing or unauthorized.');

            return self::FAILURE;
        }

        $attempted = 0;
        $failed = 0;
        $limit = max(1, min(1000, (int) $this->option('limit')));
        IntegrationExchange::query()
            ->where('direction', 'outbound')
            ->where('status', 'retry_scheduled')
            ->where('next_attempt_at', '<=', now())
            ->oldest('next_attempt_at')
            ->limit($limit)
            ->get()
            ->each(function (IntegrationExchange $exchange) use ($delivery, $actor, &$attempted, &$failed): void {
                try {
                    $delivery->handle($exchange, $actor, 'scheduled_retry');
                    $attempted++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $this->components->info("Attempted {$attempted} due exchange(s); {$failed} runner failure(s).");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
