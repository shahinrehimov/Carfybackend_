<?php

namespace App\Http\Middleware;

use App\Services\CompanyAdminService;
use App\Services\LangService;
use Closure;
use Illuminate\Http\Request;

class CompanyVerifiedMiddleware
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
        $companyAdmin = CompanyAdminService::instance()->getUser();
        $company = $companyAdmin?->company;

        if (!$company) {
            return response()->json([
                'type' => 'company_not_found',
                'message' => LangService::instance()
                    ->setDefault('Company not found!')
                    ->getLang('company_not_found'),
            ], 404);
        }

        if (!$company->verified_at) {
            return response()->json([
                'type' => 'company_not_verified',
                'message' => LangService::instance()
                    ->setDefault('Company is not verified yet!')
                    ->getLang('company_not_verified'),
            ], 403);
        }

        return $next($request);
    }
}
