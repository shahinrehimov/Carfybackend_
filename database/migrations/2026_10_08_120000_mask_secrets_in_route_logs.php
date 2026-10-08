<?php

use App\Http\Middleware\RouteLogMiddleware;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Route logs used to store request bodies verbatim, so passwords and OTP codes
 * (e.g. creating a seller admin, phone registration) sit in plain text.
 * New rows are masked by RouteLogMiddleware; this scrubs the existing ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pattern = '"(' . implode('|', RouteLogMiddleware::SENSITIVE_KEYS) . ')"';

        DB::table('route_logs')
            ->select(['id', 'requests'])
            ->whereRaw('requests ~* ?', [$pattern])
            ->orderBy('id')
            ->chunkById(500, static function ($rows) {
                foreach ($rows as $row) {
                    $data = json_decode($row->requests, true);

                    if (!is_array($data)) {
                        continue;
                    }

                    DB::table('route_logs')
                        ->where('id', $row->id)
                        ->update(['requests' => json_encode(RouteLogMiddleware::maskSensitive($data))]);
                }
            });
    }

    public function down(): void
    {
        // Masked values cannot be restored
    }
};
