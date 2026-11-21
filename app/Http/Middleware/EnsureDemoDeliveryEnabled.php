<?php

namespace App\Http\Middleware;

use App\Services\DemoDeliveryProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoDeliveryEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DemoDeliveryProvider::enabled(), 404);

        return $next($request);
    }
}
