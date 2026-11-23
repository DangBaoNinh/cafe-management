<?php

namespace App\Http\Middleware;

use App\Services\DemoPaymentGateway;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoPaymentEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DemoPaymentGateway::enabled(), 404);

        return $next($request);
    }
}
