<?php

namespace App\Http\Middleware;

use App\Models\RouteLog;
use Closure;
use Illuminate\Http\Request;
use JsonException;

class RouteLogMiddleware
{
    /**
     * Request keys (at any depth) whose values must never be stored in the log.
     */
    public const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'old_password',
        'new_password', 'new_password_confirmation', 'otp', 'token', 'access_token',
        'refresh_token', 'id_token', 'identity_token', 'authorization_code',
        'card_number', 'cvv', 'cvc',
    ];

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
                'requests' => json_encode(self::maskSensitive($request->all()), JSON_THROW_ON_ERROR),
            ]);

        return $next($request);
    }

    /**
     * Replaces secret values (passwords, OTPs, tokens) with a placeholder, recursively.
     */
    public static function maskSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::maskSensitive($value);
            }
        }

        return $data;
    }
}
