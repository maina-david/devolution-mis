<?php

namespace App\Actions;

use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReceiveDataSubjectRequest
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $metadata
     */
    public function handle(array $attributes, CarbonInterface $receivedAt, ?User $actor, array $metadata = []): DataSubjectRequest
    {
        return DB::transaction(function () use ($attributes, $receivedAt, $actor, $metadata): DataSubjectRequest {
            $privacyRequest = DataSubjectRequest::create([
                ...$attributes,
                'reference' => 'DSR-'.now()->format('Y').'-'.mb_strtoupper(Str::random(10)),
                'received_at' => $receivedAt,
                'due_at' => $receivedAt->copy()->addDays((int) config('privacy.data_subject_request_target_days')),
                'status' => 'received',
                'metadata' => [
                    ...$metadata,
                    'intake_actor_id' => $actor?->id,
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                $privacyRequest,
                'privacy.data-subject-request.received',
                __('data-governance.privacy.audit.request_received', ['reference' => $privacyRequest->reference]),
                metadata: ['intake_channel' => $metadata['intake_channel'] ?? 'internal'],
            );

            return $privacyRequest;
        });
    }
}
