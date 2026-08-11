<?php

namespace App\Console\Commands;

use App\Models\UserActivitySession;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('activity:expire-sessions')]
#[Description('Close activity sessions whose authenticated session lifetime elapsed')]
class ExpireInactiveUserActivitySessions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lifetime = (int) config('session.lifetime', 120);
        $cutoff = now()->subMinutes($lifetime);
        $expired = 0;

        UserActivitySession::query()->whereNull('logged_out_at')->where('last_seen_at', '<', $cutoff)->chunkById(200, function (Collection $sessions) use (&$expired, $lifetime): void {
            foreach ($sessions as $session) {
                $session->update(['logged_out_at' => $session->last_seen_at->addMinutes($lifetime), 'last_action' => 'auth.session_expired']);
                $expired++;
            }
        });

        $this->components->info("Closed {$expired} inactive user activity session(s).");

        return self::SUCCESS;
    }
}
