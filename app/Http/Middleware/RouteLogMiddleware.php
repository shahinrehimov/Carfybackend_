<?php

namespace App\Http\Middleware;

use App\Models\RouteLog;
use Closure;
use Illuminate\Http\Request;
use JsonException;

class RouteLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     * @throws JsonException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $companyId = null;

        // Get company_id from authenticated company admin
        if (\App\Services\CompanyAdminService::instance()->checkUser()) {
            $companyId = \App\Services\CompanyAdminService::instance()->getUser()->company_id;
        }

        RouteLog::query()
            ->create([
                'company_id' => $companyId,
                'url' => url()->current(),
                'url_full' => explode('?', url()->full())[1] ?? '',
                'ip' => $request->ip(),
                'method' => $request->method(),
                'action' => app('request')->route()?->getAction()['controller'] ?? '',
                'requests' => json_encode($request->all(), JSON_THROW_ON_ERROR),
            ]);

        return $next($request);
    }
}
