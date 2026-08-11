<?php

namespace App\Actions;

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
        abort_unless($document->scan_status === 'clean', 409, 'Quarantined evidence cannot be verified.');
        $document->update(['verification_status' => $status]);
        $recipients = User::query()->where(fn ($query) => $query->whereKey($document->uploaded_by)->orWhere('county_id', $document->county_id))->get();
        Notification::send($recipients, new ProgrammeAlert('Evidence review completed', "{$document->title} was marked {$status}.", 'evidence'));
        $this->auditLogger->record($actor, $document, "evidence.{$status}", "Evidence marked {$status}: {$document->title}.", $document->county_id);

        return $document;
    }
}
