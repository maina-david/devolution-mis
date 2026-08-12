<?php

namespace App\Http\Controllers;

use App\Actions\TransitionAssessment;
use App\Enums\AssessmentStatus;
use App\Enums\ProgrammePermission;
use App\Http\Requests\BulkAssessmentTransitionRequest;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BulkAssessmentTransitionController extends Controller
{
    public function __invoke(BulkAssessmentTransitionRequest $request, TransitionAssessment $transition): RedirectResponse
    {
        $actor = $this->user($request);
        $transitionName = $request->string('transition')->toString();
        [$permission, $from, $to] = match ($transitionName) {
            'submit' => [ProgrammePermission::SubmitAssessment, [AssessmentStatus::Draft, AssessmentStatus::EvidenceCollection], AssessmentStatus::Submitted],
            'review' => [ProgrammePermission::ReviewAssessment, [AssessmentStatus::Submitted], AssessmentStatus::UnderAssessment],
            default => abort(422, 'Unsupported assessment transition.'),
        };
        Gate::authorize($permission->value);

        $count = DB::transaction(function () use ($request, $actor, $from, $to, $transition): int {
            /** @var Collection<int, Assessment> $assessments */
            $assessments = Assessment::query()
                ->whereKey($request->ids())
                ->with('county')
                ->lockForUpdate()
                ->get();

            abort_unless($assessments->count() === count($request->ids()), 422, 'One or more selected assessments are unavailable.');
            foreach ($assessments as $assessment) {
                abort_unless($actor->canAccessCounty($assessment->county), 403);
                abort_unless(in_array($assessment->status, $from, true), 409, 'Every selected assessment must be in a valid state for this action.');
            }

            foreach ($assessments as $assessment) {
                $transition->handle($assessment, $to, $actor);
            }

            return $assessments->count();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$count} assessments moved to {$to->value}."]);

        return back();
    }

    private function user(BulkAssessmentTransitionRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
