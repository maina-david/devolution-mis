<?php

namespace App\Console\Commands;

use App\Services\WorkflowSlaMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workflows:escalate-overdue')]
#[Description('Create idempotent escalation records for workflow instances that breached their state SLA')]
class EscalateOverdueWorkflows extends Command
{
    public function handle(WorkflowSlaMonitor $monitor): int
    {
        $count = $monitor->escalateOverdue();
        $this->info("Created {$count} workflow SLA escalation(s).");

        return self::SUCCESS;
    }
}
