<?php

namespace App\Repositories;

use App\Models\Admin;
use App\Models\CompanyAdmin;
use App\Models\RouteLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class AuditLogRepository
{
    /**
     * Route log actor filter value => morph class stored in creatable_type.
     */
    private const ACTOR_TYPES = [
        'admin'  => Admin::class,
        'seller' => CompanyAdmin::class,
        'user'   => User::class,
    ];

    public function load(array $validated): LengthAwarePaginator
    {
        $actorType = $validated['actor_type'] ?? null;

        return RouteLog::query()
            ->with(['creatable', 'company'])
            ->when(
                $validated['search'] ?? null,
                fn($q, $search) => $q->where(function ($q) use ($search) {
                    $q->where('url', 'ilike', "%{$search}%")
                        ->orWhere('action', 'ilike', "%{$search}%");
                })
            )
            ->when(
                $actorType === 'guest',
                fn($q) => $q->whereNull('creatable_type')
            )
            ->when(
                $actorType && $actorType !== 'guest',
                fn($q) => $q->where('creatable_type', self::ACTOR_TYPES[$actorType])
            )
            ->when(
                $validated['actor_id'] ?? null,
                fn($q, $actorId) => $q->where('creatable_id', $actorId)
            )
            ->when(
                $validated['company_id'] ?? null,
                fn($q, $companyId) => $q->where('company_id', $companyId)
            )
            // Reads are noise for an audit trail, so only changes are listed unless asked otherwise
            ->when(
                $validated['method'] ?? null,
                fn($q, $method) => $q->where('method', $method),
                fn($q) => $q->when(
                    !($validated['include_reads'] ?? false),
                    fn($q) => $q->where('method', '!=', 'GET')
                )
            )
            ->when(
                $validated['ip'] ?? null,
                fn($q, $ip) => $q->where('ip', $ip)
            )
            ->when(
                $validated['date_from'] ?? null,
                fn($q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay())
            )
            ->when(
                $validated['date_to'] ?? null,
                fn($q, $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay())
            )
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 20);
    }
}
