<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\PartnerAgreement;
use App\Models\PartnerAgreementChangeRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreatePartnerAgreementChange
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerAgreement $agreement, User $requester, array $attributes): PartnerAgreementChangeRequest
    {
        abort_unless($requester->can(ProgrammePermission::ManagePartners->value), 403);
        abort_unless(in_array($agreement->status, ['active', 'suspended'], true), 409, __('partner-coordination.lifecycle.errors.agreement_change_state'));

        $request = DB::transaction(function () use ($agreement, $requester, $attributes): PartnerAgreementChangeRequest {
            $locked = PartnerAgreement::query()->lockForUpdate()->findOrFail($agreement->id);
            abort_if($locked->changeRequests()->whereDoesntHave('decision')->exists(), 409, __('partner-coordination.lifecycle.errors.agreement_change_pending'));
            $latestVersion = $locked->changeRequests()->max('version');
            $predecessorChecksum = $locked->changeRequests()->value('request_checksum');
            $version = (is_numeric($latestVersion) ? (int) $latestVersion : 0) + 1;
            $requestedAt = now();
            $proposed = array_filter(Arr::only($attributes, ['title', 'summary', 'ends_on', 'committed_value']), fn (mixed $value): bool => $value !== null && $value !== '');
            $snapshot = ['partner_agreement_id' => $locked->id, 'version' => $version, 'change_type' => $attributes['change_type'], 'proposed_changes' => $proposed, 'reason' => $attributes['reason'], 'effective_on' => $attributes['effective_on'], 'requested_by' => $requester->id, 'requested_at' => $requestedAt->toIso8601String(), 'predecessor_checksum' => is_string($predecessorChecksum) ? $predecessorChecksum : null];

            return $locked->changeRequests()->create([...$snapshot, 'requested_at' => $requestedAt, 'request_checksum' => $this->canonicalJson->checksum($snapshot)]);
        }, attempts: 3);

        $this->auditLogger->record($requester, $agreement, 'partner.agreement.change_requested', "{$request->change_type} request version {$request->version} recorded.", metadata: ['change_request_id' => $request->id, 'request_checksum' => $request->request_checksum]);

        return $request;
    }
}
