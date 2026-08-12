<?php

namespace App\Http\Controllers;

use App\Actions\UpdateCountyGrant;
use App\Http\Requests\UpdateGrantRequest;
use App\Models\CountyGrant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GrantController extends Controller
{
    public function update(UpdateGrantRequest $request, CountyGrant $grant, UpdateCountyGrant $updateGrant): RedirectResponse
    {
        abort_unless($this->user($request)->canAccessCounty($grant->county), 403);
        $updateGrant->handle($grant, $request->grantData(), $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grant record updated.']);

        return back();
    }

    private function user(UpdateGrantRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
