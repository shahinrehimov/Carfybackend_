<?php

namespace App\Http\Middleware;

use App\Services\CompanyAdminService;
use App\Services\LangService;
use Closure;
use Illuminate\Http\Request;

class CompanyAdminMiddleware
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
        $user = CompanyAdminService::instance()->getUser();

        if (!$user->isActive()) {
            return response()->json([
                'type' => 'not_active',
                'message' => LangService::instance()
                    ->setDefault('User is not active!')
                    ->getLang('company_admin_not_active'),
            ], 403);
        }

        if ($user->password_must_change) {
            return response()->json([
                'type' => 'password_must_change',
                'message' => LangService::instance()
                    ->setDefault('You must change your password!')
                    ->getLang('company_admin_must_change_password'),
            ], 403);
        }

        return $next($request);
    }
}
