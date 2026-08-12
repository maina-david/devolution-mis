<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use App\Models\User;
use App\Models\UserLocalePreference;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;

class LocaleController extends Controller
{
    public function __invoke(UpdateLocaleRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $locale = $request->string('locale')->toString();
        /** @var User $user */
        $user = $request->user();
        $previousLocale = $user->localePreference?->getRawOriginal('locale');
        $preference = UserLocalePreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['locale' => $locale],
        );

        $request->session()->put('locale', $locale);
        App::setLocale($locale);
        $auditLogger->record($user, $preference, 'user.locale.updated', 'User default language updated.', metadata: [
            'previous_locale' => is_string($previousLocale) ? $previousLocale : null,
            'locale' => $locale,
        ]);

        return back()->with('status', __('idmis.locale.updated'));
    }
}
