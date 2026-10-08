<?php

namespace App\Http\Resources\Admin\AuditLogs;

use Illuminate\Http\Request;

/**
 * @property string $requests
 */
class AuditLogResource extends AuditLogsResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            // Secrets are already masked when the log is written (RouteLogMiddleware)
            'payload' => json_decode((string) $this->requests, true),
        ]);
    }
}
