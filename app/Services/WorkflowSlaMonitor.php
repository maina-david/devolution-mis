<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkflowEscalation;
use App\Models\WorkflowInstance;
use App\Notifications\ProgrammeAlert;
use Illuminate\Support\Collection;

class WorkflowSlaMonitor
{
    public function __construct(private ProgrammeCountyScope $countyScope) {}

    public function escalateOverdue(): int
    {
        $created = 0;

        WorkflowInstance::query()
            ->with('version:id,configuration')
            ->where('status', 'active')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->eachById(function (WorkflowInstance $instance) use (&$created): void {
                $recipient = data_get($instance->version->configuration, 'escalation_user_id');
                $escalation = WorkflowEscalation::firstOrCreate(
                    [
                        'workflow_instance_id' => $instance->id,
                        'reason' => 'sla_breach',
                        'state_entered_at' => $instance->state_entered_at,
                    ],
                    [
                        'level' => $instance->escalations()->count() + 1,
                        'status' => 'open',
                        'escalated_to' => is_string($recipient) ? $recipient : null,
                        'due_at' => $instance->due_at,
                        'triggered_at' => now(),
                        'metadata' => ['state' => $instance->current_state, 'overdue_seconds' => (int) $instance->due_at?->diffInSeconds(now())],
                    ],
                );

                if ($escalation->wasRecentlyCreated) {
                    $created++;
                    $this->notifyRecipients($instance);
                }
            });

        return $created;
    }

    private function notifyRecipients(WorkflowInstance $instance): void
    {
        $recipients = $this->recipients($instance);

        foreach ($recipients as $recipient) {
            $url = $recipient->currentTeam ? route('workflows.index', $recipient->currentTeam->slug) : null;
            $recipient->notify(new ProgrammeAlert(
                title: 'Workflow SLA breached',
                message: "A {$instance->current_state} workflow state passed its deadline and requires attention.",
                category: 'workflow_sla',
                url: $url,
            ));
        }
    }

    /** @return Collection<int, User> */
    private function recipients(WorkflowInstance $instance): Collection
    {
        $explicitUserId = data_get($instance->version->configuration, 'escalation_user_id');
        if (is_string($explicitUserId)) {
            return User::query()->whereKey($explicitUserId)->get();
        }

        $permission = data_get($instance->version->configuration, 'escalation_permission');
        if (! is_string($permission) || $permission === '') {
            return collect();
        }

        return User::query()->permission($permission)->get()
            ->filter(fn (User $user): bool => $instance->county_id === null || $this->countyScope->query($user)->whereKey($instance->county_id)->exists())
            ->values();
    }
}
