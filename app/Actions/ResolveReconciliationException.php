<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\ReconciliationException;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class ResolveReconciliationException
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(ReconciliationException $exception, User $actor, string $resolution): ReconciliationException
    {
        abort_unless($actor->can(ProgrammePermission::ResolveIntegrationExceptions->value), 403, __('integrations.reconciliation.errors.resolve_unauthorized'));

        return DB::transaction(function () use ($exception, $actor, $resolution): ReconciliationException {
            $exception = ReconciliationException::query()->with('county')->lockForUpdate()->findOrFail($exception->id);
            abort_unless($exception->county === null || $actor->canAccessCounty($exception->county), 403, __('integrations.reconciliation.errors.county_scope'));
            abort_unless($exception->status === 'open', 409, __('integrations.reconciliation.errors.open_required'));
            $exception->update(['status' => 'resolved', 'resolution' => $resolution, 'resolved_by' => $actor->id, 'resolved_at' => now()]);
            $run = $exception->run;
            if (! $run->exceptions()->where('status', 'open')->exists()) {
                $result = ['source_count' => $run->source_count, 'target_count' => $run->target_count, 'matched_count' => $run->matched_count, 'exception_count' => $run->exception_count];
                $run->update(['status' => 'reconciled', 'result_checksum' => hash('sha256', json_encode($result, JSON_THROW_ON_ERROR)), 'completed_at' => now()]);
            }
            $this->auditLogger->record($actor, $exception, 'integration.exception.resolved', __('integrations.reconciliation.audit.resolved'), $exception->county_id, ['resolution' => $resolution]);

            return $exception->refresh();
        });
    }
}
