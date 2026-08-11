<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use App\Services\SupportTicketAccess;
use Illuminate\Support\Facades\DB;

class TransitionSupportTicket
{
    public function __construct(private SupportTicketAccess $access, private RecordSupportTicketActivity $recordActivity, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(SupportTicket $supportTicket, User $actor, array $attributes): SupportTicket
    {
        abort_unless($this->access->allows($actor, $supportTicket), 403);

        $ticket = DB::transaction(function () use ($supportTicket, $actor, $attributes): SupportTicket {
            $ticket = SupportTicket::query()->lockForUpdate()->whereKey($supportTicket->id)->sole();
            $transition = (string) $attributes['transition'];
            $allowed = [
                'start' => ['triaged'],
                'request_information' => ['in_progress'],
                'provide_information' => ['awaiting_requester'],
                'resolve' => ['in_progress'],
                'close' => ['resolved'],
                'reopen' => ['resolved'],
            ];
            abort_unless(isset($allowed[$transition]) && in_array($ticket->status, $allowed[$transition], true), 409, 'This support transition is not allowed from the current state.');
            $this->authorizeTransition($ticket, $actor, $transition);
            $fromStatus = $ticket->status;
            $changes = match ($transition) {
                'start' => ['status' => 'in_progress'],
                'request_information' => ['status' => 'awaiting_requester'],
                'provide_information' => ['status' => 'in_progress'],
                'resolve' => ['status' => 'resolved', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'resolution_summary' => $attributes['resolution_summary']],
                'close' => ['status' => 'closed', 'closed_by' => $actor->id, 'closed_at' => now()],
                'reopen' => ['status' => 'in_progress', 'resolved_by' => null, 'resolved_at' => null, 'resolution_summary' => null],
            };
            $ticket->update([...$changes, 'last_activity_at' => now(), 'reminder_sent_at' => null]);
            $this->recordActivity->handle($ticket, $actor, $transition, $fromStatus, $ticket->status, (string) $attributes['narrative']);
            $this->auditLogger->record($actor, $ticket, 'support.ticket.'.$transition, "Service-desk ticket {$ticket->reference} advanced from {$fromStatus} to {$ticket->status}.", $ticket->county_id, ['from_status' => $fromStatus, 'to_status' => $ticket->status]);

            return $ticket;
        });

        $ticket->load(['requester:id,name', 'assignee:id,name']);
        $recipient = in_array($ticket->status, ['awaiting_requester', 'resolved'], true) ? $ticket->requester : $ticket->assignee;
        if ($recipient !== null && $recipient->id !== $actor->id) {
            $recipient->notify(new ProgrammeAlert('Support ticket updated', "{$ticket->reference} is now ".str($ticket->status)->headline().'.', 'support-desk'));
        }

        return $ticket->refresh();
    }

    private function authorizeTransition(SupportTicket $ticket, User $actor, string $transition): void
    {
        if (in_array($transition, ['provide_information', 'close', 'reopen'], true)) {
            abort_unless($actor->id === $ticket->requester_id, 403, 'Only the requester may perform this transition.');

            return;
        }

        abort_unless($actor->can(ProgrammePermission::ResolveSupportTickets->value) && $actor->id === $ticket->assigned_to, 403, 'Only the assigned support resolver may perform this transition.');
        if ($transition === 'resolve') {
            abort_if($actor->id === $ticket->requester_id, 403, 'Resolution requires separation from the requester.');
        }
    }
}
