<?php

namespace App\Http\Middleware;

use App\Enums\SupportedLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! is_string($locale)) {
            $preference = $request->user()?->localePreference()->value('locale');
            $locale = is_string($preference) && SupportedLocale::tryFrom($preference) !== null
                ? $preference
                : config('app.locale');
            $request->session()->put('locale', $locale);
        }

        if (! is_string($locale) || SupportedLocale::tryFrom($locale) === null) {
            $locale = config('app.fallback_locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
