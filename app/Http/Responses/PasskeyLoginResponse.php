<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use RedirectsAfterAuthentication;

    public function toResponse($request): Response
    {
        $redirect = $this->authenticatedRedirectPath($request, Fortify::redirects('login'));

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => redirect()->intended($redirect)->getTargetUrl()], 200)
            : redirect()->intended($redirect);
    }
}
