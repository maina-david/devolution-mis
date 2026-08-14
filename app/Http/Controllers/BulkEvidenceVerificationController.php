<?php

namespace App\Http\Controllers;

use App\Actions\VerifyAssessmentEvidence;
use App\Http\Requests\BulkEvidenceVerificationRequest;
use App\Models\AssessmentDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BulkEvidenceVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(BulkEvidenceVerificationRequest $request, VerifyAssessmentEvidence $verify): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $status = $request->string('status')->toString();
        $count = DB::transaction(function () use ($request, $status, $actor, $verify): int {
            /** @var Collection<int, AssessmentDocument> $documents */
            $documents = AssessmentDocument::query()
                ->with('county')
                ->whereIn('id', $request->ids())
                ->lockForUpdate()
                ->get();

            abort_unless($documents->count() === count($request->ids()), 404, __('assessment-record.bulk.errors.evidence_unavailable'));
            foreach ($documents as $document) {
                abort_unless($actor->canAccessCounty($document->county), 403, __('assessment-record.bulk.errors.evidence_scope'));
                abort_unless($document->scan_status === 'clean', 409, __('assessment-record.bulk.errors.evidence_quarantined'));
            }

            foreach ($documents as $document) {
                $verify->handle($document, $status, $actor);
            }

            return $documents->count();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('assessment-record.bulk.outcomes.evidence_reviewed', $count, ['count' => $count, 'status' => __('assessment-record.evidence_statuses.'.$status)])]);

        return back();
    }
}
