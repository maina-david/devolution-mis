<?php

namespace App\Actions;

use App\Models\PartnerAgreement;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TransitionPartnerAgreement
{
    public function __construct(private TransitionWorkflow $transitionWorkflow, private AuditLogger $auditLogger) {}

    public function handle(PartnerAgreement $agreement, User $actor, string $transition, ?string $comment = null): PartnerAgreement
    {
        abort_unless($agreement->workflow !== null, 409, 'This agreement has no governed workflow.');
        $documentCount = $agreement->documentLinks()->whereHas('document', fn ($query) => $query->whereNull('deleted_at'))->count();

        return DB::transaction(function () use ($agreement, $actor, $transition, $comment, $documentCount): PartnerAgreement {
            $workflow = $this->transitionWorkflow->handle($agreement->workflow, $transition, $actor, ['document_count' => $documentCount], $comment);
            $attributes = ['status' => $workflow->current_state];
            if ($transition === 'approve') {
                $attributes = [...$attributes, 'approved_by' => $actor->id, 'approved_at' => now()];
            }
            $agreement->update($attributes);
            $this->auditLogger->record($actor, $agreement, "partner.agreement.{$transition}", "Partner agreement {$agreement->reference} transitioned to {$workflow->current_state}.", $workflow->county_id, ['comment' => $comment, 'document_count' => $documentCount]);

            return $agreement->refresh();
        });
    }
}
