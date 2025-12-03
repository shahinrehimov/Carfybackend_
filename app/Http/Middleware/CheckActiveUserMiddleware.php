<?php

namespace App\Http\Middleware;

use App\Http\Resources\GeneralResource;
use App\Services\LangService;
use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;

class CheckActiveUserMiddleware
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
        $user = UserService::instance()->getUser();

        if (!$user->isActive()) {
            return response()->json(GeneralResource::make([
                'message' => LangService::instance()
                    ->setDefault('Your account is not active. Please contact support!')
                    ->getLang('your_account_is_not_active'),
            ]), 403);
        }

        return $next($request);
    }
}
