<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\AssessmentDocument;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Notification;

class VerifyAssessmentEvidence
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(AssessmentDocument $document, string $status, User $actor): AssessmentDocument
    {
        abort_unless($actor->can(ProgrammePermission::ReviewAssessment->value) && $actor->canAccessCounty($document->county), 403, __('assessment-record.errors.evidence_verification_unauthorized'));
        abort_unless($document->scan_status === 'clean', 409, __('assessment-record.errors.evidence_quarantined'));
        $document->update(['verification_status' => $status]);
        $recipients = User::query()->where(fn ($query) => $query->whereKey($document->uploaded_by)->orWhere('county_id', $document->county_id))->get();
        Notification::send($recipients, ProgrammeAlert::translated('assessment-record.notifications.evidence_reviewed_title', 'assessment-record.notifications.evidence_reviewed_message', 'evidence', messageParameters: ['title' => $document->title, 'status' => __('assessment-record.evidence_statuses.'.$status)]));
        $this->auditLogger->record($actor, $document, "evidence.{$status}", __('assessment-record.audit.evidence_reviewed', ['title' => $document->title, 'status' => __('assessment-record.evidence_statuses.'.$status)]), $document->county_id);

        return $document;
    }
}
