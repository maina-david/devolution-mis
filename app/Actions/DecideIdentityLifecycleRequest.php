<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class DecideIdentityLifecycleRequest
{
    public function __construct(private ApplyApprovedIdentityLifecycleRequest $apply, private AuditLogger $auditLogger) {}

    /** @param array{decision:string, rationale:string} $attributes */
    public function handle(IdentityLifecycleRequest $request, User $actor, array $attributes): IdentityLifecycleRequest
    {
        abort_unless($actor->can(ProgrammePermission::CertifyAccess->value), 403, __('security.identity_lifecycle.errors.decision_unauthorized'));

        $request = DB::transaction(function () use ($request, $actor, $attributes): IdentityLifecycleRequest {
            $request = IdentityLifecycleRequest::query()->with('user')->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === 'pending', 409, __('security.identity_lifecycle.errors.already_decided'));
            abort_if($request->requested_by === $actor->id, 403, __('security.identity_lifecycle.errors.requester_separation'));
            abort_if($request->user_id === $actor->id, 403, __('security.identity_lifecycle.errors.target_separation'));

            $decisionEvidence = ['request_id' => $request->id, 'source_checksum' => $request->source_checksum, 'decision' => $attributes['decision'], 'rationale' => $attributes['rationale'], 'decided_by' => $actor->id, 'effective_at' => $request->effective_at->toIso8601String()];
            $request->update(['status' => $attributes['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_by' => $actor->id, 'decision_rationale' => $attributes['rationale'], 'decided_at' => now(), 'evidence_checksum' => hash('sha256', json_encode($decisionEvidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $this->auditLogger->record($actor, $request, 'security.identity-lifecycle.decided', __('security.identity_lifecycle.audit.decided', ['event' => __("security.identity_lifecycle.values.events.{$request->event_type}"), 'decision' => __("security.identity_lifecycle.values.decisions.{$attributes['decision']}")]), $request->user->county_id, ['source_event_id' => $request->source_event_id, 'target_user_id' => $request->user_id, 'effective_at' => $request->effective_at->toIso8601String()]);

            return $request->refresh();
        });

        if ($attributes['decision'] === 'approve' && ! $request->effective_at->isFuture()) {
            $this->apply->handle($request, $actor, 'interactive_approval');
        }

        return $request->refresh();
    }
}
