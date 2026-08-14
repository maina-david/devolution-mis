<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateProgrammeUser;
use App\Http\Requests\BulkProgrammeUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BulkProgrammeUserController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(BulkProgrammeUserRequest $request, DeactivateProgrammeUser $deactivate): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $targets = User::query()->with('roles')->whereIn('id', $request->ids())->get();
        abort_unless($targets->count() === count($request->ids()), 404, __('access-control.errors.bulk_users_missing'));

        foreach ($targets as $target) {
            abort_if($actor->is($target), 409, __('access-control.errors.bulk_self_deactivation'));
            abort_unless($deactivate->allows($actor, $target), 403);
        }

        DB::transaction(function () use ($targets, $actor, $deactivate): void {
            foreach ($targets as $target) {
                $deactivate->handle($target, $actor);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('access-control.outcomes.bulk_deactivated', $targets->count(), ['count' => $targets->count()])]);

        return back();
    }
}
