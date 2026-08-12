<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    use RedirectsAfterAuthentication;

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false], 201)
            : redirect()->intended($this->authenticatedRedirectPath($request, Fortify::redirects('register')));
    }
}
