<?php

namespace App\Http\Controllers;

use App\Actions\DeactivateProgrammeUser;
use App\Actions\GrantProgrammeAccess;
use App\Http\Requests\StoreProgrammeUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgrammeUserController extends Controller
{
    public function store(StoreProgrammeUserRequest $request, string $currentTeam, GrantProgrammeAccess $grantAccess): RedirectResponse
    {
        $grantAccess->handle($request->accessData(), $this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User access granted and password setup requested.']);

        return back();
    }

    public function destroy(Request $request, string $currentTeam, User $programmeUser, DeactivateProgrammeUser $deactivate): RedirectResponse
    {
        $actor = $this->user($request);
        $deactivate->handle($programmeUser, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User access deactivated.']);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
