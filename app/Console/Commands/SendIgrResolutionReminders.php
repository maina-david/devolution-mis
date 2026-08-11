<?php

namespace App\Console\Commands;

use App\Models\IgrResolution;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('igr:send-reminders')]
#[Description('Send idempotent upcoming and overdue IGR resolution reminders')]
class SendIgrResolutionReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        IgrResolution::query()->whereNotIn('status', ['closed'])->whereDate('due_on', '<=', today()->addDays(7))->whereNull('reminder_sent_at')
            ->with('assignments.user')->chunkById(100, function ($resolutions): void {
                foreach ($resolutions as $resolution) {
                    $timing = $resolution->due_on->isPast() ? 'overdue' : 'due soon';
                    $resolution->assignments->pluck('user')->filter()->unique('id')->each(fn (User $user) => $user->notifyNow(new ProgrammeAlert('IGR resolution '.$timing, "{$resolution->resolution_number} is {$timing}; due {$resolution->due_on->toFormattedDateString()}.", 'igr-resolutions')));
                    $resolution->update(['reminder_sent_at' => now()]);
                }
            });

        return self::SUCCESS;
    }
}
