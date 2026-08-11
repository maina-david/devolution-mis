<?php

namespace App\Console\Commands;

use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dswg:send-reminders')]
#[Description('Send idempotent reminders for upcoming DSWG meetings and due accountable actions')]
class SendDswgReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $meetingReminders = 0;
        DswgMeeting::query()
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addDay()])
            ->with('invitees')
            ->chunkById(100, function ($meetings) use (&$meetingReminders): void {
                foreach ($meetings as $meeting) {
                    if (! $meeting->update(['reminder_sent_at' => now()])) {
                        continue;
                    }
                    $meeting->invitees()
                        ->wherePivotNotIn('invitation_status', ['declined'])
                        ->get()
                        ->each(fn (User $user) => $user->notifyNow(new ProgrammeAlert('DSWG meeting reminder', "{$meeting->title} begins {$meeting->starts_at->diffForHumans()}.", 'dswg')));
                    $meetingReminders++;
                }
            });

        $actionReminders = 0;
        DswgAction::query()
            ->whereNotIn('status', ['completed'])
            ->whereNull('reminder_sent_at')
            ->whereDate('due_on', '<=', today()->addDays(3))
            ->with('accountableUser')
            ->chunkById(100, function ($actions) use (&$actionReminders): void {
                foreach ($actions as $action) {
                    if (! $action->update(['reminder_sent_at' => now()])) {
                        continue;
                    }
                    $action->accountableUser->notifyNow(new ProgrammeAlert('DSWG action deadline', "{$action->code} is due {$action->due_on->diffForHumans()}.", 'dswg'));
                    $actionReminders++;
                }
            });

        $this->components->info("Sent {$meetingReminders} meeting reminder(s) and {$actionReminders} action reminder(s).");

        return self::SUCCESS;
    }
}
