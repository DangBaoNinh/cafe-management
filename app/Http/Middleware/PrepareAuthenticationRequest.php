<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrepareAuthenticationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register.store', 'login.store', 'password.email', 'password.update')) {
            return $next($request);
        }

        $isPasswordReset = $request->routeIs('password.email', 'password.update');
        // Fortify lowercases the address before validating some of these requests.
        $rules = ['email' => $isPasswordReset ? ['required', 'string', 'email'] : ['required', 'string']];

        if ($request->routeIs('password.update')) {
            $rules['token'] = ['required', 'string'];
        }

        $request->validate($rules);

        if (! $isPasswordReset) {
            return $next($request);
        }

        // Use the same user row for both issuing and consuming a reset token.
        $user = new User;
        $connection = $user->getConnection();
        $connection->beginTransaction();

        try {
            $user->newQuery()->where('email', Str::lower($request->string('email')->toString()))
                ->lockForUpdate()->first();

            $response = $next($request);

            // The routing pipeline may render a thrown exception before returning here.
            if (isset($response->exception)) {
                $connection->rollBack();
            } else {
                $connection->commit();
            }

            return $response;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
