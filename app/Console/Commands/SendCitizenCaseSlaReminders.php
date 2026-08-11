<?php

namespace App\Console\Commands;

use App\Enums\ProgrammePermission;
use App\Models\CitizenCase;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('citizen-cases:send-sla-reminders')]
#[Description('Send idempotent first-response and resolution SLA alerts')]
class SendCitizenCaseSlaReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        CitizenCase::query()->whereNotIn('status', ['resolved', 'closed'])->whereNull('reminder_sent_at')->where(fn ($query) => $query->where('first_response_due_at', '<=', now()->addHours(4))->orWhere('resolution_due_at', '<=', now()->addDay()))->with('assignee')->chunkById(100, function ($cases): void {
            foreach ($cases as $case) {
                $recipients = User::permission(ProgrammePermission::ManageCitizenCases->value)->where(fn ($query) => $query->where('county_id', $case->county_id)->orWhereNull('county_id'))->get();
                if ($case->assignee) {
                    $recipients->push($case->assignee);
                }
                $overdue = $case->resolution_due_at->isPast();
                $recipients->unique('id')->each(fn (User $user) => $user->notify(new ProgrammeAlert($overdue ? 'Citizen case overdue' : 'Citizen case SLA approaching', "{$case->reference}: {$case->subject}", 'citizen-cases')));
                $case->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? now() : $case->escalated_at]);
            }
        });

        return self::SUCCESS;
    }
}
