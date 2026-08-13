<?php

namespace App\Http\Controllers;

use App\Actions\BulkTriageCitizenCases;
use App\Http\Requests\BulkTriageCitizenCasesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BulkCitizenCaseTriageController extends Controller
{
    public function __invoke(BulkTriageCitizenCasesRequest $request, BulkTriageCitizenCases $triage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $count = $triage->handle($user, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('citizen.casework.outcomes.bulk_triaged', ['count' => $count])]);

        return back();
    }
}
