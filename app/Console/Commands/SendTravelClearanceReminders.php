<?php

namespace App\Console\Commands;

use App\Enums\ProgrammePermission;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('travel-clearance:send-reminders')]
#[Description('Send idempotent travel-clearance deadline reminders and escalations')]
class SendTravelClearanceReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AuditLogger $auditLogger): int
    {
        $sent = 0;
        TravelRequest::query()->whereIn('status', ['manager_review', 'finance_review'])->whereNull('reminder_sent_at')->whereNotNull('decision_due_at')->where('decision_due_at', '<=', now()->addDay())->with('requester:id,name')->chunkById(100, function ($requests) use (&$sent, $auditLogger): void {
            foreach ($requests as $travelRequest) {
                $overdue = $travelRequest->decision_due_at->isPast();
                $permission = $travelRequest->status === 'manager_review' ? ProgrammePermission::ApproveTravelRequests : ProgrammePermission::FinanceClearTravel;
                $reviewers = User::permission($permission->value)->when($travelRequest->county_id !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('county_id')->orWhere('county_id', $travelRequest->county_id)))->get();
                collect([$travelRequest->requester])->merge($reviewers)->filter()->unique('id')->each(fn (User $user) => $user->notify(new ProgrammeAlert($overdue ? 'Travel clearance overdue' : 'Travel clearance due soon', "{$travelRequest->reference}: {$travelRequest->purpose}", 'travel-clearance')));
                $travelRequest->update(['reminder_sent_at' => now(), 'escalated_at' => $overdue ? now() : null]);
                $auditLogger->record(null, $travelRequest, $overdue ? 'travel.request.escalated' : 'travel.request.reminded', $overdue ? 'Overdue travel-clearance decision escalated.' : 'Upcoming travel-clearance deadline reminder sent.', $travelRequest->county_id);
                $sent++;
            }
        });
        $this->components->info("Sent {$sent} travel-clearance reminder(s).");

        return self::SUCCESS;
    }
}
