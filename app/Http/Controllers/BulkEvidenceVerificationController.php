<?php

namespace App\Http\Controllers;

use App\Actions\VerifyAssessmentEvidence;
use App\Http\Requests\BulkEvidenceVerificationRequest;
use App\Models\AssessmentDocument;
use App\Models\User;
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
        $documents = AssessmentDocument::query()->with('county')->whereIn('id', $request->ids())->get();
        abort_unless($documents->count() === count($request->ids()), 404, 'One or more selected documents no longer exist.');

        foreach ($documents as $document) {
            abort_unless($actor->canAccessCounty($document->county), 403);
            abort_unless($document->scan_status === 'clean', 409, 'Bulk verification contains quarantined evidence.');
        }

        $status = $request->string('status')->toString();
        DB::transaction(function () use ($documents, $status, $actor, $verify): void {
            foreach ($documents as $document) {
                $verify->handle($document, $status, $actor);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => $documents->count()." evidence records marked {$status}."]);

        return back();
    }
}
