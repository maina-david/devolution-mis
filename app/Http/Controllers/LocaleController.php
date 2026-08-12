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
        /** @var User|null $user */
        $user = $request->user();
        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        if ($user !== null) {
            $previousLocale = $user->localePreference?->getRawOriginal('locale');
            $preference = UserLocalePreference::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['locale' => $locale],
            );
            $auditLogger->record($user, $preference, 'user.locale.updated', __('idmis.locale.audit_updated'), metadata: [
                'previous_locale' => is_string($previousLocale) ? $previousLocale : null,
                'locale' => $locale,
            ]);
        }

        return back()->with('status', __($user === null ? 'idmis.locale.session_updated' : 'idmis.locale.updated'));
    }
}
