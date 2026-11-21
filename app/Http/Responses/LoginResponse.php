<?php

namespace App\Http\Responses;

use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse extends FortifyLoginResponse
{
    public function toResponse($request): Response
    {
        if (! $request->wantsJson() && $request->user()?->can('access-admin')) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return parent::toResponse($request);
    }
}
