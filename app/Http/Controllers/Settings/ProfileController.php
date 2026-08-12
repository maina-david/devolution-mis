<?php

namespace App\Http\Controllers\Settings;

use App\Actions\UpdateProfilePhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfilePhotoUpdateRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'profile' => [
                'role' => $this->user($request)->programmeRole()->label(),
                'county' => $this->user($request)->county?->identityCell(),
                'assignedCounties' => $this->user($request)->assignedCounties()->orderBy('code')->get()->map->identityCell()->values(),
                'hasPhoto' => $this->user($request)->profile_photo_path !== null,
                'photoUpdatedAt' => $this->user($request)->profile_photo_updated_at?->toIso8601String(),
                'accountCreatedAt' => $this->user($request)->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, UpdateProfilePhoto $updateProfilePhoto): RedirectResponse
    {
        $user = $this->user($request);

        $updateProfilePhoto->remove($user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function storePhoto(ProfilePhotoUpdateRequest $request, UpdateProfilePhoto $updateProfilePhoto): RedirectResponse
    {
        $updateProfilePhoto->store($this->user($request), $request->photo());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo updated.')]);

        return to_route('profile.edit');
    }

    public function destroyPhoto(Request $request, UpdateProfilePhoto $updateProfilePhoto): RedirectResponse
    {
        $updateProfilePhoto->remove($this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile photo removed.')]);

        return to_route('profile.edit');
    }

    public function photo(Request $request): HttpResponse
    {
        $user = $this->user($request);
        abort_unless($user->profile_photo_disk && $user->profile_photo_path && $user->profile_photo_checksum, 404);
        abort_unless(Storage::disk($user->profile_photo_disk)->exists($user->profile_photo_path), 404);

        $content = Storage::disk($user->profile_photo_disk)->get($user->profile_photo_path);
        abort_unless(hash_equals($user->profile_photo_checksum, hash('sha256', $content)), 409, 'The profile photo failed integrity verification.');

        $etag = '"'.$user->profile_photo_checksum.'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => 'private, max-age=86400']);
        }

        return response($content, 200, [
            'Content-Type' => 'image/webp',
            'Content-Length' => (string) strlen($content),
            'Content-Disposition' => 'inline; filename="profile-photo.webp"',
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
