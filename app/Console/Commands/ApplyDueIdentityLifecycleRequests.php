<?php

namespace App\Console\Commands;

use App\Actions\ApplyApprovedIdentityLifecycleRequest;
use App\Enums\ProgrammePermission;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

#[Signature('security:apply-due-identity-lifecycle {--limit=250 : Maximum due approved events to inspect}')]
#[Description('Apply independently approved joiner, mover and leaver events at their effective time')]
class ApplyDueIdentityLifecycleRequests extends Command
{
    public const RunnerLock = 'security:apply-due-identity-lifecycle';

    public function handle(ApplyApprovedIdentityLifecycleRequest $apply): int
    {
        $email = config('security-governance.identity_lifecycle_service_user_email');
        if (! is_string($email) || $email === '') {
            $this->components->warn(__('security.identity_lifecycle.console.inactive'));

            return self::SUCCESS;
        }

        $actor = User::query()->where('email', $email)->first();
        if (! $actor instanceof User || $actor->access_revoked_at !== null || ! $actor->can(ProgrammePermission::ManageSecurityGovernance->value)) {
            $this->components->error(__('security.identity_lifecycle.console.service_identity_unauthorized'));

            return self::FAILURE;
        }

        $limit = max(1, min(1000, (int) $this->option('limit')));
        $lock = Cache::lock(self::RunnerLock, 300);
        if (! $lock->get()) {
            $this->components->warn(__('security.identity_lifecycle.console.already_running'));

            return self::SUCCESS;
        }

        try {
            return $this->applyDue($apply, $actor, $limit);
        } finally {
            $lock->release();
        }
    }

    private function applyDue(ApplyApprovedIdentityLifecycleRequest $apply, User $actor, int $limit): int
    {
        $maximumAttempts = max(1, (int) config('security-governance.identity_lifecycle_max_application_attempts', 5));
        $applied = 0;
        $exceptions = 0;
        IdentityLifecycleRequest::query()->whereIn('status', ['approved', 'application_exception'])->where('effective_at', '<=', now())->where('application_attempts', '<', $maximumAttempts)->oldest('effective_at')->limit($limit)->get()->each(function (IdentityLifecycleRequest $request) use ($apply, $actor, &$applied, &$exceptions): void {
            try {
                if ($apply->handle($request, $actor, 'scheduled_reconciliation')) {
                    $applied++;
                } else {
                    $exceptions++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $exceptions++;
            }
        });

        $this->components->info(__('security.identity_lifecycle.console.summary', ['applied' => $applied, 'exceptions' => $exceptions]));

        return $exceptions === 0 ? self::SUCCESS : self::FAILURE;
    }
}
