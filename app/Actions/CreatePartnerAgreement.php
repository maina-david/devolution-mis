<?php

namespace App\Actions;

use App\Models\PartnerAgreement;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreatePartnerAgreement
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(PartnerProfile $partner, User $actor, array $attributes): PartnerAgreement
    {
        abort_unless($actor->programmeRole()->hasNationalScope() || $partner->counties()->get()->contains(fn ($county): bool => $actor->canAccessCounty($county)), 403);
        $definition = WorkflowDefinition::query()->where('code', 'PARTNER-AGREEMENT-LIFECYCLE')->where('status', 'active')->firstOrFail();

        return DB::transaction(function () use ($partner, $actor, $attributes, $definition): PartnerAgreement {
            $agreement = $partner->agreements()->create([
                ...Arr::except($attributes, ['partner_profile_id', 'status']),
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);
            $countyId = $partner->counties()->orderBy('code')->value('counties.id');
            $workflow = $this->startWorkflow->handle($definition, $agreement, $actor, ['document_count' => 0], $countyId);
            $agreement->update(['workflow_instance_id' => $workflow->id]);
            $this->auditLogger->record($actor, $agreement, 'partner.agreement.created', __('partner-coordination.lifecycle.audit.agreement_created', ['reference' => $agreement->reference]), $countyId);

            return $agreement->refresh();
        });
    }
}
