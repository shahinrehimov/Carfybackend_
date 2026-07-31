<?php

namespace App\Repositories;

use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Company;
use App\Models\CompanyProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ControlStatisticsRepository
{
    use Concerns\StatisticsQueries;

    // ─── Order Statistics ────────────────────────────────────────────────────

    public function getOrderStats(array $validated): array
    {
        $period    = $this->pgPeriod($validated['period'] ?? 'daily');
        $dateFrom  = $validated['date_from'] ?? null;
        $dateTo    = $validated['date_to'] ?? null;
        $status    = $validated['status'] ?? null;
        $companyId = $validated['company_id'] ?? null;

        $base    = $this->orderBaseQuery($dateFrom, $dateTo, $status, $companyId);
        $revenue = $this->revenueCondition($status !== null);

        $summary = (clone $base)
            ->selectRaw("
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.amount_final) FILTER (WHERE {$revenue}), 0) as total_revenue_cents,
                COALESCE(SUM(oi.quantity)     FILTER (WHERE {$revenue}), 0) as total_quantity
            ")
            ->first();

        $chart = (clone $base)
            ->selectRaw("
                DATE_TRUNC('{$period}', o.created_at) as period_date,
                COUNT(DISTINCT o.id)                  as order_count,
                COALESCE(SUM(oi.amount_final) FILTER (WHERE {$revenue}), 0) as revenue_cents,
                COALESCE(SUM(oi.quantity)     FILTER (WHERE {$revenue}), 0) as quantity
            ")
            ->groupByRaw("DATE_TRUNC('{$period}', o.created_at)")
            ->orderByRaw("DATE_TRUNC('{$period}', o.created_at) ASC")
            ->get();

        return [
            'summary' => [
                'total_orders'   => (int) ($summary->total_orders ?? 0),
                'total_revenue'  => round(($summary->total_revenue_cents ?? 0) / 100, 2),
                'total_quantity' => (int) ($summary->total_quantity ?? 0),
            ],
            'chart' => $chart->map(fn($row) => [
                'date'        => substr($row->period_date, 0, $period === 'month' ? 7 : 10),
                'order_count' => (int) $row->order_count,
                'revenue'     => round($row->revenue_cents / 100, 2),
                'quantity'    => (int) $row->quantity,
            ])->values(),
        ];
    }

    // ─── Top Stores ──────────────────────────────────────────────────────────

    public function getTopStores(array $validated): Collection
    {
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $limit    = $validated['limit'] ?? 10;

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->join('companies as c', 'c.id', '=', 'cpv.company_id')
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereIn('o.status', OrderStatusEnum::revenueValues())
            ->where('oi.status', '!=', OrderItemStatusEnum::CANCELED->value)
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('
                c.id                              as company_id,
                COALESCE(SUM(oi.quantity), 0)     as quantity_sold,
                COALESCE(SUM(oi.amount_final), 0) as revenue_cents,
                COUNT(DISTINCT o.id)              as order_count
            ')
            ->groupBy('c.id')
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get();

        $companies = Company::query()
            ->whereIn('id', $rows->pluck('company_id')->all())
            ->with(['translatable'])
            ->get()
            ->keyBy('id');

        return $rows->map(fn($row) => [
            'company'       => $this->formatCompany($companies->get($row->company_id)),
            'quantity_sold' => (int) $row->quantity_sold,
            'revenue'       => round($row->revenue_cents / 100, 2),
            'order_count'   => (int) $row->order_count,
        ]);
    }

    // ─── Top Products ────────────────────────────────────────────────────────

    public function getTopProducts(array $validated): array
    {
        $dateFrom  = $validated['date_from'] ?? null;
        $dateTo    = $validated['date_to'] ?? null;
        $limit     = $validated['limit'] ?? 10;
        $companyId = $validated['company_id'] ?? null;

        $selling = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->whereIn('o.status', OrderStatusEnum::revenueValues())
            ->where('oi.status', '!=', OrderItemStatusEnum::CANCELED->value)
            ->when($companyId, fn($q) => $q->where('cpv.company_id', $companyId))
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('
                cpv.id                            as cpv_id,
                COALESCE(SUM(oi.quantity), 0)     as quantity_sold,
                COALESCE(SUM(oi.amount_final), 0) as revenue_cents,
                COUNT(DISTINCT o.id)              as order_count
            ')
            ->groupBy('cpv.id')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        $viewed = DB::table('company_product_variant_views as v')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'v.company_product_variant_id')
            ->whereNull('cpv.deleted_at')
            ->when($companyId, fn($q) => $q->where('cpv.company_id', $companyId))
            ->when($dateFrom, fn($q) => $q->where('v.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('v.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('cpv.id as cpv_id, COUNT(v.id) as view_count')
            ->groupBy('cpv.id')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get();

        $cpvs = $this->loadCpvsForDisplay(
            $selling->pluck('cpv_id')->merge($viewed->pluck('cpv_id'))->unique()->values()->all()
        );

        return [
            'top_selling' => $selling->map(fn($row) => [
                'cpv'           => $this->formatCpv($cpvs->get($row->cpv_id)),
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue'       => round($row->revenue_cents / 100, 2),
                'order_count'   => (int) $row->order_count,
            ])->values(),
            'top_viewed' => $viewed->map(fn($row) => [
                'cpv'        => $this->formatCpv($cpvs->get($row->cpv_id)),
                'view_count' => (int) $row->view_count,
            ])->values(),
        ];
    }

    // ─── User Statistics ─────────────────────────────────────────────────────

    public function getUserStats(array $validated): array
    {
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $period   = $this->pgPeriod($validated['period'] ?? 'daily');

        $registeredTotal = DB::table('users')
            ->whereNull('deleted_at')
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('created_at', '<=', $dateTo . ' 23:59:59'))
            ->count();

        // Users who obtained an access token in the period (i.e. logged in)
        $activeViaLogin = DB::table('users as u')
            ->join('personal_access_tokens as t', function ($join) {
                $join->on('t.tokenable_id', '=', 'u.id')
                    ->where('t.tokenable_type', '=', (new User())->getMorphClass());
            })
            ->whereNull('u.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('t.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('t.created_at', '<=', $dateTo . ' 23:59:59'))
            ->distinct()
            ->count('u.id');

        $activeViaOrder = DB::table('users as u')
            ->join('orders as o', 'o.user_id', '=', 'u.id')
            ->whereNull('u.deleted_at')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', OrderStatusEnum::placedValues())
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->distinct()
            ->count('u.id');

        $chart = DB::table('users')
            ->whereNull('deleted_at')
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw("DATE_TRUNC('{$period}', created_at) as period_date, COUNT(*) as count")
            ->groupByRaw("DATE_TRUNC('{$period}', created_at)")
            ->orderByRaw("DATE_TRUNC('{$period}', created_at) ASC")
            ->get();

        return [
            'summary' => [
                'registered'        => $registeredTotal,
                'active_via_login'  => $activeViaLogin,
                'active_via_order'  => $activeViaOrder,
            ],
            'registration_chart' => $chart->map(fn($row) => [
                'date'  => substr($row->period_date, 0, $period === 'month' ? 7 : 10),
                'count' => (int) $row->count,
            ])->values(),
        ];
    }

    // ─── Cancellation Statistics ──────────────────────────────────────────────

    /**
     * Cancellations happen on two levels: the whole order (customer / admin) and
     * a single item (a seller dropping their part of a multi-seller order). Both
     * are reported, so `total_cancelled` counts fully cancelled orders plus the
     * orders that lost at least one item while staying alive.
     */
    public function getCancellations(array $validated): array
    {
        $dateFrom  = $validated['date_from'] ?? null;
        $dateTo    = $validated['date_to'] ?? null;
        $companyId = $validated['company_id'] ?? null;
        $limit     = $validated['limit'] ?? 10;

        $orderBase = DB::table('orders as o')
            ->whereNull('o.deleted_at')
            ->where('o.status', OrderStatusEnum::CANCELED->value)
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->when($companyId, fn($q) => $q
                ->whereExists(fn($sub) => $sub
                    ->from('order_items as oi')
                    ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
                    ->whereColumn('oi.order_id', 'o.id')
                    ->whereNull('oi.deleted_at')
                    ->whereNull('cpv.deleted_at')
                    ->where('cpv.company_id', $companyId)
                ));

        $itemBase = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->where('oi.status', OrderItemStatusEnum::CANCELED->value)
            // Items of a fully cancelled order are already covered by $orderBase
            ->where('o.status', '!=', OrderStatusEnum::CANCELED->value)
            ->when($companyId, fn($q) => $q->where('cpv.company_id', $companyId))
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'));

        $cancelledOrders = (clone $orderBase)->count('o.id');
        $cancelledItems  = (clone $itemBase)->distinct()->count('oi.id');

        $byActor = collect()
            ->merge(
                (clone $orderBase)
                    ->whereNotNull('o.cancelled_by_type')
                    ->selectRaw('o.cancelled_by_type as actor_type, COUNT(DISTINCT o.id) as count')
                    ->groupBy('o.cancelled_by_type')
                    ->get()
            )
            ->merge(
                (clone $itemBase)
                    ->whereNotNull('oi.cancelled_by_type')
                    ->selectRaw('oi.cancelled_by_type as actor_type, COUNT(DISTINCT oi.id) as count')
                    ->groupBy('oi.cancelled_by_type')
                    ->get()
            )
            ->groupBy(fn($row) => $this->actorLabel($row->actor_type))
            ->map(fn($rows, $actor) => [
                'actor' => $actor,
                'count' => (int) $rows->sum('count'),
            ])
            ->sortByDesc('count')
            ->values();

        $topReasons = collect()
            ->merge(
                (clone $orderBase)
                    ->whereNotNull('o.cancel_reason')
                    ->selectRaw('o.cancel_reason as reason, COUNT(DISTINCT o.id) as count')
                    ->groupBy('o.cancel_reason')
                    ->get()
            )
            ->merge(
                (clone $itemBase)
                    ->whereNotNull('oi.cancel_reason')
                    ->selectRaw('oi.cancel_reason as reason, COUNT(DISTINCT oi.id) as count')
                    ->groupBy('oi.cancel_reason')
                    ->get()
            )
            ->groupBy('reason')
            ->map(fn($rows, $reason) => [
                'reason' => $reason,
                'count'  => (int) $rows->sum('count'),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values();

        return [
            'total_cancelled'          => $cancelledOrders + $cancelledItems,
            'cancelled_orders'         => $cancelledOrders,
            'cancelled_items'          => $cancelledItems,
            'by_actor'                 => $byActor,
            'top_reasons'              => $topReasons,
        ];
    }

    // ─── Commission Statistics ────────────────────────────────────────────────

    /**
     * What the platform earned on the sellers' sales.
     *
     * Reads the snapshot frozen on each order item at checkout, so a rate edit
     * today never changes what last month reports. Only revenue-eligible rows
     * count — the platform earns nothing on a cancelled or returned line.
     */
    public function getCommissions(array $validated): array
    {
        $period    = $this->pgPeriod($validated['period'] ?? 'daily');
        $dateFrom  = $validated['date_from'] ?? null;
        $dateTo    = $validated['date_to'] ?? null;
        $companyId = $validated['company_id'] ?? null;
        $limit     = $validated['limit'] ?? 10;

        $base = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->whereIn('o.status', OrderStatusEnum::revenueValues())
            ->where('oi.status', '!=', OrderItemStatusEnum::CANCELED->value)
            ->when($companyId, fn($q) => $q->where('cpv.company_id', $companyId))
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'));

        $summary = (clone $base)
            ->selectRaw('
                COALESCE(SUM(oi.commission_amount), 0) as commission_cents,
                COALESCE(SUM(oi.amount_final), 0)      as revenue_cents,
                COUNT(*) FILTER (WHERE oi.commission_amount IS NULL) as items_without_snapshot
            ')
            ->first();

        $commissionCents = (int) ($summary->commission_cents ?? 0);
        $revenueCents    = (int) ($summary->revenue_cents ?? 0);

        $chart = (clone $base)
            ->selectRaw("
                DATE_TRUNC('{$period}', o.created_at) as period_date,
                COALESCE(SUM(oi.commission_amount), 0) as commission_cents,
                COALESCE(SUM(oi.amount_final), 0)      as revenue_cents
            ")
            ->groupByRaw("DATE_TRUNC('{$period}', o.created_at)")
            ->orderByRaw("DATE_TRUNC('{$period}', o.created_at) ASC")
            ->get();

        $byCompanyRows = (clone $base)
            ->selectRaw('
                cpv.company_id                         as company_id,
                COALESCE(SUM(oi.commission_amount), 0) as commission_cents,
                COALESCE(SUM(oi.amount_final), 0)      as revenue_cents
            ')
            ->groupBy('cpv.company_id')
            ->orderByDesc('commission_cents')
            ->limit($limit)
            ->get();

        $companies = Company::query()
            ->whereIn('id', $byCompanyRows->pluck('company_id')->all())
            ->with(['translatable'])
            ->get()
            ->keyBy('id');

        return [
            'summary' => [
                'total_commission' => round($commissionCents / 100, 2),
                'total_revenue'    => round($revenueCents / 100, 2),
                // Effective take rate across the period, not the configured one
                'effective_rate'   => $revenueCents > 0
                    ? round($commissionCents / $revenueCents * 100, 2)
                    : 0,
                // > 0 means historical rows still need app:backfill-order-commissions
                'items_without_snapshot' => (int) ($summary->items_without_snapshot ?? 0),
            ],
            // Kept for backwards compatibility with the original flat response
            'total_commission' => round($commissionCents / 100, 2),
            'chart' => $chart->map(fn($row) => [
                'date'       => substr($row->period_date, 0, $period === 'month' ? 7 : 10),
                'commission' => round($row->commission_cents / 100, 2),
                'revenue'    => round($row->revenue_cents / 100, 2),
            ])->values(),
            'by_company' => $byCompanyRows->map(fn($row) => [
                'company'    => $this->formatCompany($companies->get($row->company_id)),
                'commission' => round($row->commission_cents / 100, 2),
                'revenue'    => round($row->revenue_cents / 100, 2),
            ])->values(),
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function orderBaseQuery(?string $dateFrom, ?string $dateTo, ?string $status, ?int $companyId): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->when($companyId, fn($q) => $q->where('cpv.company_id', $companyId))
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->when(
                $status,
                fn($q) => $q->where('o.status', $status),
                fn($q) => $q->whereIn('o.status', OrderStatusEnum::placedValues())
            );
    }

    private function formatCompany(?Company $company): ?array
    {
        if (!$company) {
            return null;
        }

        return [
            'id'   => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->getLang('title'),
        ];
    }

    private function actorLabel(?string $morphClass): string
    {
        return class_basename($morphClass ?? 'Unknown');
    }

    private function loadCpvsForDisplay(array $cpvIds): Collection
    {
        if (empty($cpvIds)) {
            return collect();
        }

        return CompanyProductVariant::query()
            ->whereIn('id', $cpvIds)
            ->with($this->cpvDisplayRelations())
            ->get()
            ->keyBy('id');
    }
}
