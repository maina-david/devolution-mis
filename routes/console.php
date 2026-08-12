<?php

use App\Console\Commands\ApplyDueIdentityLifecycleRequests;
use App\Console\Commands\CreateDatabaseBackup;
use App\Console\Commands\EscalateOverduePrivacyIncidents;
use App\Console\Commands\EscalateOverdueWorkflows;
use App\Console\Commands\EscalateSecurityIncidents;
use App\Console\Commands\ExpireInactiveUserActivitySessions;
use App\Console\Commands\GenerateDswgRecurringMeetingsCommand;
use App\Console\Commands\MonitorPartnerOperationalAlerts;
use App\Console\Commands\MonitorSupportTicketSlas;
use App\Console\Commands\ReconcileAccessDelegations;
use App\Console\Commands\ReconcilePartnerContributionExchangesCommand;
use App\Console\Commands\RecordOperationalMeasurement;
use App\Console\Commands\RecoverPendingDocumentExtractions;
use App\Console\Commands\RetryIntegrationExchanges;
use App\Console\Commands\RunAuditAssuranceCommand;
use App\Console\Commands\RunScheduledReports;
use App\Console\Commands\SendCitizenCaseSlaReminders;
use App\Console\Commands\SendDswgReminders;
use App\Console\Commands\SendEvaluationFindingReminders;
use App\Console\Commands\SendIgrResolutionReminders;
use App\Console\Commands\SendPartnerCollaborationActionReminders;
use App\Console\Commands\SendPerformancePlanReminders;
use App\Console\Commands\SendTravelClearanceReminders;
use App\Console\Commands\VerifyDatabaseBackup;
use Illuminate\Support\Facades\Schedule;

Schedule::command(EscalateOverdueWorkflows::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Escalate overdue workflow state SLAs');

Schedule::command(EscalateOverduePrivacyIncidents::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send controlled personal-data breach deadline reminders and escalations');

Schedule::command(EscalateSecurityIncidents::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Escalate governed security incident acknowledgement and containment targets');

Schedule::command(ReconcileAccessDelegations::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Activate and expire governed delegated and emergency access');

Schedule::command(ExpireInactiveUserActivitySessions::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Close inactive authenticated activity sessions at the configured session lifetime');

Schedule::command(ApplyDueIdentityLifecycleRequests::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Apply independently approved identity lifecycle events at their effective time');

Schedule::command(SendDswgReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent DSWG meeting and accountable-action reminders');

Schedule::command(GenerateDswgRecurringMeetingsCommand::class)
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Maintain the governed DSWG recurring-meeting generation horizon');

Schedule::command(SendIgrResolutionReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent IGR resolution deadline reminders');

Schedule::command(SendCitizenCaseSlaReminders::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send citizen feedback and grievance SLA alerts');

Schedule::command(SendPerformancePlanReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent departmental performance deadline reminders and escalations');

Schedule::command(SendEvaluationFindingReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent evaluation-recommendation deadline reminders and escalations');

Schedule::command(SendTravelClearanceReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent travel-clearance deadline reminders and escalations');

Schedule::command(MonitorPartnerOperationalAlerts::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Detect idempotent partner agreement and contribution operational alerts');

Schedule::command(MonitorSupportTicketSlas::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent service-desk response and resolution SLA reminders');

Schedule::command(SendPartnerCollaborationActionReminders::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Send idempotent partner collaboration-action reminders and escalations');

Schedule::command(ReconcilePartnerContributionExchangesCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Reconcile inbound partner contribution statements under published contracts');

Schedule::command(RetryIntegrationExchanges::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Retry due outbound integration exchanges under published backoff policies');

Schedule::command('passport:purge --revoked --expired --hours=168')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Purge expired and revoked OAuth evidence after the configured operational window');

Schedule::command(RecordOperationalMeasurement::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Record application, queue and recovery objective measurements');

Schedule::command(RunAuditAssuranceCommand::class)
    ->dailyAt('00:30')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->description('Verify and retain checksum-bound audit-chain assurance evidence');

Schedule::command(RunScheduledReports::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Queue independently approved analytics report schedules');

Schedule::command(RecoverPendingDocumentExtractions::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Recover pending and retryable document text extractions');

Schedule::command(CreateDatabaseBackup::class)
    ->dailyAt('01:00')
    ->withoutOverlapping(120)
    ->onOneServer()
    ->description('Create the daily checksummed PostgreSQL backup');

Schedule::command(VerifyDatabaseBackup::class, ['--restore-probe'])
    ->weeklyOn(7, '02:00')
    ->withoutOverlapping(240)
    ->onOneServer()
    ->description('Restore the latest backup into an isolated temporary database and verify it');
