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
            $request->session()->forget('url.intended');

            return redirect()->route('operations.table-sessions.index');
        }

        if (! $request->wantsJson() && $request->user()?->role === UserRole::Customer) {
            $intended = $request->session()->pull('url.intended');
            if (is_string($intended) && str_starts_with($intended, url('/').'/')
                && in_array(parse_url($intended, PHP_URL_PATH), [parse_url(route('reservations.create'), PHP_URL_PATH), parse_url(route('spaces'), PHP_URL_PATH)], true)) {
                return redirect()->to($intended);
            }
        }

        return $request->wantsJson()
            ? parent::toResponse($request)
            : redirect()->route('home');
    }
}
