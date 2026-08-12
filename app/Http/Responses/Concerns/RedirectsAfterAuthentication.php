<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;

trait RedirectsAfterAuthentication
{
    protected function authenticatedRedirectPath(Request $request, string $redirect): string
    {
        abort_if(! $request->user(), 403);

        return $redirect;
    }
}
