<?php

namespace App\Http\Controllers;

use App\Actions\CreateIdentityLifecycleRequest;
use App\Actions\DecideIdentityLifecycleRequest;
use App\Http\Requests\DecideIdentityLifecycleRequest as DecideRequest;
use App\Http\Requests\StoreIdentityLifecycleRequest;
use App\Models\IdentityLifecycleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class IdentityLifecycleController extends Controller
{
    public function store(StoreIdentityLifecycleRequest $request, CreateIdentityLifecycleRequest $action): RedirectResponse
    {
        $action->handle($this->user($request), $request->lifecycleData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('security.outcomes.lifecycle_staged')]);

        return back();
    }

    public function decide(DecideRequest $request, IdentityLifecycleRequest $identityLifecycleRequest, DecideIdentityLifecycleRequest $action): RedirectResponse
    {
        $action->handle($identityLifecycleRequest, $this->user($request), $request->decisionData());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('security.outcomes.lifecycle_decided')]);

        return back();
    }

    private function user(StoreIdentityLifecycleRequest|DecideRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
