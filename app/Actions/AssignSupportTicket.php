<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\SupportTicketAccess;
use Illuminate\Support\Facades\DB;

class AssignSupportTicket
{
    public function __construct(private SupportTicketAccess $access, private RecordSupportTicketActivity $recordActivity, private AuditLogger $auditLogger) {}

    public function handle(SupportTicket $supportTicket, User $manager, User $assignee, string $narrative): SupportTicket
    {
        abort_unless($manager->can(ProgrammePermission::ManageSupportTickets->value) && $this->access->allows($manager, $supportTicket), 403);
        abort_unless($assignee->can(ProgrammePermission::ResolveSupportTickets->value), 422, 'The selected assignee is not authorized to resolve support tickets.');
        abort_if($assignee->id === $supportTicket->requester_id, 422, 'A requester cannot resolve their own support ticket.');
        $this->access->assertCounty($assignee, $supportTicket->county_id);

        $ticket = DB::transaction(function () use ($supportTicket, $manager, $assignee, $narrative): SupportTicket {
            $ticket = SupportTicket::query()->lockForUpdate()->whereKey($supportTicket->id)->sole();
            abort_unless(in_array($ticket->status, ['open', 'triaged'], true), 409, 'Only open or triaged tickets may be assigned.');
            $fromStatus = $ticket->status;
            $ticket->update([
                'assigned_to' => $assignee->id,
                'status' => 'triaged',
                'first_responded_at' => $ticket->first_responded_at ?? now(),
                'last_activity_at' => now(),
                'reminder_sent_at' => null,
            ]);
            $this->recordActivity->handle($ticket, $manager, 'assigned', $fromStatus, 'triaged', $narrative, ['assigned_to' => $assignee->id, 'assigned_to_name' => $assignee->name]);
            $this->auditLogger->record($manager, $ticket, 'support.ticket.assigned', "Service-desk ticket {$ticket->reference} assigned to {$assignee->name}.", $ticket->county_id, ['assigned_to' => $assignee->id, 'from_status' => $fromStatus]);

            return $ticket;
        });

        $assignee->notify(new ProgrammeAlert('Support ticket assigned', "{$ticket->reference}: {$ticket->subject}", 'support-desk'));

        return $ticket->refresh();
    }
}
