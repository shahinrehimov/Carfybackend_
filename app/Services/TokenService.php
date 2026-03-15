<?php

namespace App\Services;

use Jenssegers\Agent\Agent;
use Laravel\Sanctum\NewAccessToken;
use Stevebauman\Location\Facades\Location;

class TokenService
{
    private static ?TokenService $instance = null;

    private function __construct() {}

    public static function instance(): TokenService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function resolveDeviceName(?string $clientProvided): string
    {
        if (!empty($clientProvided)) {
            return $clientProvided;
        }

        $agent = new Agent();
        $agent->setUserAgent(request()->userAgent());

        $parts = array_filter([
            $agent->platform() ?: null,
            $agent->browser() ?: null,
        ]);

        if ($parts) {
            return implode(' / ', $parts);
        }

        $rawUserAgent = request()->userAgent();

        return $rawUserAgent ? mb_substr($rawUserAgent, 0, 100) : 'Unknown';
    }

    public function fillTokenMeta(NewAccessToken $newToken): void
    {
        $ip = request()->ip();
        $locationData = Location::get();
        $location = null;

        if ($locationData) {
            $parts = array_filter([$locationData->cityName, $locationData->countryName]);
            $location = implode(', ', $parts) ?: null;
        }

        $newToken->accessToken->update([
            'ip_address' => $ip,
            'location'   => $location,
        ]);
    }
}
