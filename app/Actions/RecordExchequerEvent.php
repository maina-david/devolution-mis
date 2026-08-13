<?php

namespace App\Actions;

use App\Models\CountyGrant;
use App\Models\ExchequerEvent;
use App\Models\ExchequerRequest;
use App\Models\IntegrationExchange;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordExchequerEvent
{
    /** @var array<string, array{stage: string, status: string, sources: list<string>}> */
    private const EVENTS = ['submitted_to_treasury' => ['stage' => 'submitted_to_treasury', 'status' => 'open', 'sources' => ['IDMIS', 'TREASURY']], 'treasury_forwarded_ocob' => ['stage' => 'forwarded_to_ocob', 'status' => 'open', 'sources' => ['TREASURY']], 'ocob_authorized' => ['stage' => 'authorized_by_ocob', 'status' => 'open', 'sources' => ['OCOB']], 'treasury_issued_cbk' => ['stage' => 'issued_to_cbk', 'status' => 'open', 'sources' => ['TREASURY']], 'cbk_credited' => ['stage' => 'credited', 'status' => 'completed', 'sources' => ['CBK']], 'returned' => ['stage' => 'returned', 'status' => 'returned', 'sources' => ['TREASURY', 'OCOB']], 'exception' => ['stage' => 'exception', 'status' => 'exception', 'sources' => ['IDMIS', 'TREASURY', 'OCOB', 'CBK']]];

    /** @var array<string, list<string>> */
    private const ALLOWED = ['prepared' => ['submitted_to_treasury', 'exception'], 'submitted_to_treasury' => ['treasury_forwarded_ocob', 'returned', 'exception'], 'forwarded_to_ocob' => ['ocob_authorized', 'returned', 'exception'], 'authorized_by_ocob' => ['treasury_issued_cbk', 'exception'], 'issued_to_cbk' => ['cbk_credited', 'exception']];

    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(ExchequerRequest $request, User $actor, array $attributes): ExchequerEvent
    {
        return DB::transaction(function () use ($request, $actor, $attributes): ExchequerEvent {
            $request = ExchequerRequest::query()->with('county')->lockForUpdate()->findOrFail($request->id);
            abort_unless($actor->canAccessCounty($request->county), 403);
            abort_if($request->created_by === $actor->id, 403, __('exchequer.creator_cannot_attest'));
            $eventType = (string) $attributes['event_type'];
            $source = (string) $attributes['source_system'];
            if (! in_array($eventType, self::ALLOWED[$request->current_stage] ?? [], true)) {
                throw ValidationException::withMessages(['event_type' => __('exchequer.invalid_stage_transition')]);
            }
            if (! in_array($source, self::EVENTS[$eventType]['sources'], true)) {
                throw ValidationException::withMessages(['source_system' => __('exchequer.invalid_attesting_source')]);
            }
            $occurredAt = Carbon::parse($attributes['occurred_at']);
            if ($request->last_event_at && $occurredAt->isBefore($request->last_event_at)) {
                throw ValidationException::withMessages(['occurred_at' => __('exchequer.event_precedes_timeline')]);
            }
            $exchange = $this->exchange($request, $source, $attributes['integration_exchange_id'] ?? null);
            $origin = $request->created_at;
            $previous = $request->last_event_at ?? $origin;
            $canonical = ['request' => $request->request_reference, 'event' => $eventType, 'source' => $source, 'source_reference' => $attributes['source_event_reference'], 'occurred_at' => $occurredAt->toIso8601String(), 'exchange_checksum' => $exchange?->payload_checksum];
            $event = ExchequerEvent::create(['exchequer_request_id' => $request->id, 'integration_exchange_id' => $exchange?->id, 'recorded_by' => $actor->id, 'source_system' => $source, 'event_type' => $eventType, 'source_event_reference' => $attributes['source_event_reference'], 'occurred_at' => $occurredAt, 'received_at' => now(), 'elapsed_from_previous_minutes' => max(0, (int) $previous->diffInMinutes($occurredAt)), 'elapsed_total_minutes' => max(0, (int) $origin->diffInMinutes($occurredAt)), 'notes' => $attributes['notes'] ?? null, 'evidence_checksum' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $definition = self::EVENTS[$eventType];
            $stageDueAt = $definition['status'] === 'open' ? $occurredAt->copy()->addHours($this->slaHours($definition['stage'])) : null;
            $request->update(['current_stage' => $definition['stage'], 'status' => $definition['status'], 'last_event_at' => $occurredAt, 'stage_due_at' => $stageDueAt, 'credited_at' => $eventType === 'cbk_credited' ? $occurredAt : null]);
            if ($eventType === 'cbk_credited') {
                $grant = CountyGrant::query()->lockForUpdate()->findOrFail($request->county_grant_id);
                $disbursed = min((float) $grant->allocated_amount, (float) $grant->disbursed_amount + (float) $request->amount);
                $grant->update(['disbursed_amount' => $disbursed, 'status' => $disbursed >= (float) $grant->allocated_amount ? 'disbursed' : 'processing']);
            }
            $this->auditLogger->record($actor, $event, 'exchequer.event.recorded', __('exchequer.event_recorded_audit', ['source' => $source, 'event' => $eventType, 'reference' => $request->request_reference]), $request->county_id, ['request_id' => $request->id, 'checksum' => $event->evidence_checksum, 'elapsed_total_minutes' => $event->elapsed_total_minutes]);

            return $event;
        });
    }

    private function exchange(ExchequerRequest $request, string $source, mixed $exchangeId): ?IntegrationExchange
    {
        if (! is_string($exchangeId) || $exchangeId === '') {
            return null;
        }
        $exchange = IntegrationExchange::query()->with('contract.system')->findOrFail($exchangeId);
        if ($exchange->county_id !== null && $exchange->county_id !== $request->county_id) {
            throw ValidationException::withMessages(['integration_exchange_id' => __('exchequer.exchange_wrong_county')]);
        }
        $expectedPrefix = $source === 'TREASURY' ? 'IFMIS' : $source;
        if (! str_starts_with($exchange->contract->system->code, $expectedPrefix)) {
            throw ValidationException::withMessages(['integration_exchange_id' => __('exchequer.exchange_source_mismatch')]);
        }

        return $exchange;
    }

    private function slaHours(string $stage): int
    {
        return match ($stage) {
            'submitted_to_treasury' => 24, 'forwarded_to_ocob' => 48, 'authorized_by_ocob', 'issued_to_cbk' => 24, default => 24
        };
    }
}
