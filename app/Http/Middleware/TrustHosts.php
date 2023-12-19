<?php

namespace App\Http\Middleware;
use Closure;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function handle($request, Closure $next)
    {
        $allowedHosts = ['https://app3s-staging.web.app/', 'https://app3s-staging.web.app'];

        if (!in_array($request->getHost(), $allowedHosts)) {
            abort(403);
        }

        return $next($request);
    }
}
