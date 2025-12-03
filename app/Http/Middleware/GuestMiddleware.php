<?php

namespace App\Http\Middleware;

use App\Services\GuestService;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;

class GuestMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!UserService::instance()->checkUser()) {
            $identifier = $request->header('identifier');

            if ($identifier) {
                GuestService::instance()->setGuestByIdentifier($identifier);
            }
        }

        return $next($request);
    }
}
