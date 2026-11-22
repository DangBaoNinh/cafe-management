<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse extends FortifyLoginResponse
{
    public function toResponse($request): Response
    {
        if (! $request->wantsJson() && $request->user()?->can('access-admin')) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if (! $request->wantsJson() && $request->user()?->role === UserRole::Staff) {
            return redirect()->intended(route('operations.orders.index'));
        }

        return $request->wantsJson()
            ? parent::toResponse($request)
            : redirect()->route('home');
    }
}
