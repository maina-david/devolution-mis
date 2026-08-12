<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    use RedirectsAfterAuthentication;

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect()->intended($this->authenticatedRedirectPath($request, Fortify::redirects('email-verification')).'?verified=1');
    }
}
