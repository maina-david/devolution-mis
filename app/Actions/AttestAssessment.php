<?php

namespace App\Actions;

use App\Models\Assessment;
use App\Models\AssessmentAttestation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttestAssessment
{
    public function __construct(private CanonicalJson $canonicalJson, private AuditLogger $auditLogger) {}

    public function handle(Assessment $assessment, User $actor, string $title, string $statement): AssessmentAttestation
    {
        if ($assessment->completeness_percentage < 100.0) {
            throw ValidationException::withMessages(['attestation' => 'All mandatory evidence must be complete before county attestation.']);
        }

        $attestation = DB::transaction(function () use ($assessment, $actor, $title, $statement): AssessmentAttestation {
            $locked = Assessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $attestation = $locked->attestations()->create(['attested_by' => $actor->id, 'attestor_title' => $title, 'statement' => $statement, 'content_checksum' => $this->canonicalJson->checksum(['assessment_id' => $locked->id, 'scorecard_version_id' => $locked->assessment_scorecard_version_id, 'score' => $locked->score, 'statement' => $statement]), 'attested_at' => now()]);
            $locked->update(['attestation_status' => 'attested']);

            return $attestation;
        }, attempts: 3);
        $this->auditLogger->record($actor, $attestation, 'assessment.attested', 'County assessment attested.', $assessment->county_id, ['checksum' => $attestation->content_checksum]);

        return $attestation;
    }
}
