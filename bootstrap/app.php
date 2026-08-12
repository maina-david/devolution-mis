<?php

use App\Enums\SupportedLocale;
use App\Http\Middleware\EnsureActiveAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            EnsureActiveAccess::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            if (! in_array($response->statusCode(), [403, 404, 419, 429, 500, 503], true)) {
                return null;
            }

            $status = $response->statusCode();
            $sessionLocale = $response->request->hasSession()
                ? $response->request->session()->get('locale')
                : null;

            if (is_string($sessionLocale) && SupportedLocale::tryFrom($sessionLocale) !== null) {
                App::setLocale($sessionLocale);
            }

            return $response->render('error', [
                'status' => $status,
                'title' => trans("idmis.errors.{$status}.title"),
                'description' => trans("idmis.errors.{$status}.description"),
                'goBackLabel' => trans('idmis.errors.actions.go_back'),
            ])->withSharedData();
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
