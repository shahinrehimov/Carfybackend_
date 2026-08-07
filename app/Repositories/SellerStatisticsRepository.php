<?php

namespace App\Repositories;

use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\CompanyProductVariant;
use App\Models\CompanyProductVariantView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerStatisticsRepository
{
    use Concerns\StatisticsQueries;

    /**
     * A repeated hit on the same listing by the same visitor within this many
     * minutes counts as one view, so a page refresh does not inflate the stats.
     */
    private const VIEW_DEDUPE_MINUTES = 30;

    // ─── Order Statistics ────────────────────────────────────────────────────

    public function getOrderStats(int $companyId, array $validated): array
    {
        $period   = $this->pgPeriod($validated['period'] ?? 'daily');
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $status   = $validated['status'] ?? null;

        $base    = $this->orderBaseQuery($companyId, $dateFrom, $dateTo, $status);
        $revenue = $this->revenueCondition($status !== null);

        $summary = (clone $base)
            ->selectRaw("
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.amount_final - COALESCE(oi.commission_amount, 0)) FILTER (WHERE {$revenue}), 0) as total_revenue_cents,
                COALESCE(SUM(oi.quantity)     FILTER (WHERE {$revenue}), 0) as total_quantity
            ")
            ->first();

        $chart = (clone $base)
            ->selectRaw("
                DATE_TRUNC('{$period}', o.created_at) as period_date,
                COUNT(DISTINCT o.id)                  as order_count,
                COALESCE(SUM(oi.amount_final - COALESCE(oi.commission_amount, 0)) FILTER (WHERE {$revenue}), 0) as revenue_cents,
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

    // ─── Top Selling Products ────────────────────────────────────────────────

    public function getTopSelling(int $companyId, array $validated): Collection
    {
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $limit    = $validated['limit'] ?? 10;

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->whereIn('o.status', OrderStatusEnum::revenueValues())
            ->where('oi.status', '!=', OrderItemStatusEnum::CANCELED->value)
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('
                cpv.id as cpv_id,
                COALESCE(SUM(oi.quantity), 0) as quantity_sold,
                COALESCE(SUM(oi.amount_final - COALESCE(oi.commission_amount, 0)), 0) as revenue_cents,
                COUNT(DISTINCT oi.order_id) as order_count
            ')
            ->groupBy('cpv.id')
            // cpv.id keeps rows with equal sales in the same order the admin
            // panel lists them, whatever plan Postgres picks.
            ->orderByDesc('quantity_sold')
            ->orderBy('cpv.id')
            ->limit($limit)
            ->get();

        $cpvs = $this->loadCpvsForDisplay($rows->pluck('cpv_id')->all());

        return $rows->map(fn($row) => [
            'cpv'           => $this->formatCpv($cpvs->get($row->cpv_id)),
            'quantity_sold' => (int) $row->quantity_sold,
            'revenue'       => round($row->revenue_cents / 100, 2),
            'order_count'   => (int) $row->order_count,
        ]);
    }

    // ─── Top Viewed Products ─────────────────────────────────────────────────

    public function getTopViewed(int $companyId, array $validated): Collection
    {
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $limit    = $validated['limit'] ?? 10;

        $rows = DB::table('company_product_variant_views as v')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'v.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('v.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('v.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('cpv.id as cpv_id, COUNT(v.id) as view_count')
            ->groupBy('cpv.id')
            // cpv.id keeps rows with equal view counts in the same order the
            // admin panel lists them, whatever plan Postgres picks.
            ->orderByDesc('view_count')
            ->orderBy('cpv.id')
            ->limit($limit)
            ->get();

        $cpvs = $this->loadCpvsForDisplay($rows->pluck('cpv_id')->all());

        return $rows->map(fn($row) => [
            'cpv'        => $this->formatCpv($cpvs->get($row->cpv_id)),
            'view_count' => (int) $row->view_count,
        ]);
    }

    // ─── Customer Behavior ───────────────────────────────────────────────────

    /**
     * Basket rows are soft deleted once their order is paid (see
     * BasketService::clearBasketForOrder) as well as when the customer removes
     * the item by hand, so trashed rows must be part of every count here.
     * A basket counts as converted when an order item points back at it.
     */
    public function getCustomerBehavior(int $companyId, array $validated): array
    {
        $dateFrom       = $validated['date_from'] ?? null;
        $dateTo         = $validated['date_to'] ?? null;
        $abandonedLimit = $validated['abandoned_limit'] ?? 10;

        $totalViews = DB::table('company_product_variant_views as v')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'v.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('v.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('v.created_at', '<=', $dateTo . ' 23:59:59'))
            ->count();

        $basketRows = $this->basketBaseQuery($companyId, $dateFrom, $dateTo)
            ->selectRaw('
                cpv.id as cpv_id,
                COUNT(DISTINCT b.id) as basket_count,
                COUNT(DISTINCT oi.id) as converted_count
            ')
            ->groupBy('cpv.id')
            ->get();

        $totalBasketAdds  = (int) $basketRows->sum('basket_count');
        $convertedBaskets = (int) $basketRows->sum('converted_count');

        $abandonedRows = $basketRows
            ->map(function ($row) {
                $basketCount    = (int) $row->basket_count;
                $convertedCount = (int) $row->converted_count;

                return (object) [
                    'cpv_id'          => $row->cpv_id,
                    'basket_count'    => $basketCount,
                    'converted_count' => $convertedCount,
                    'abandoned_count' => $basketCount - $convertedCount,
                ];
            })
            ->filter(fn($row) => $row->abandoned_count > 0)
            ->sortByDesc('abandoned_count')
            ->take($abandonedLimit)
            ->values();

        $cpvs = $this->loadCpvsForDisplay($abandonedRows->pluck('cpv_id')->all());

        $abandonedProducts = $abandonedRows->map(fn($row) => [
            'cpv'              => $this->formatCpv($cpvs->get($row->cpv_id)),
            'basket_count'     => $row->basket_count,
            'order_count'      => $row->converted_count,
            'abandoned_count'  => $row->abandoned_count,
            'abandonment_rate' => $row->basket_count > 0
                ? round($row->abandoned_count / $row->basket_count * 100, 2)
                : 0,
        ])->values();

        return [
            'overview' => [
                'total_views'          => (int) $totalViews,
                'total_basket_adds'    => $totalBasketAdds,
                'converted_baskets'    => $convertedBaskets,
                'view_to_basket_rate'  => $totalViews > 0
                    ? round($totalBasketAdds / $totalViews * 100, 2)
                    : 0,
                'basket_to_order_rate' => $totalBasketAdds > 0
                    ? round($convertedBaskets / $totalBasketAdds * 100, 2)
                    : 0,
                'view_to_order_rate'   => $totalViews > 0
                    ? round($convertedBaskets / $totalViews * 100, 2)
                    : 0,
            ],
            'abandoned_products' => $abandonedProducts,
        ];
    }

    // ─── View Tracking ───────────────────────────────────────────────────────

    public function trackView(CompanyProductVariant $cpv, \Illuminate\Http\Request $request): void
    {
        $userId          = $request->user()?->id;
        $guestIdentifier = $request->header('X-Guest-Identifier');

        if ($this->hasRecentView($cpv, $userId, $guestIdentifier, $request->ip())) {
            return;
        }

        CompanyProductVariantView::create([
            'company_product_variant_id' => $cpv->id,
            'user_id'                    => $userId,
            'guest_identifier'           => $guestIdentifier,
            'ip_address'                 => $request->ip(),
            'user_agent'                 => $request->userAgent(),
            'created_at'                 => now(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function hasRecentView(CompanyProductVariant $cpv, ?int $userId, ?string $guestIdentifier, ?string $ip): bool
    {
        return CompanyProductVariantView::query()
            ->where('company_product_variant_id', $cpv->id)
            ->where('created_at', '>=', now()->subMinutes(self::VIEW_DEDUPE_MINUTES))
            ->where(function ($query) use ($userId, $guestIdentifier, $ip) {
                if ($userId) {
                    $query->orWhere('user_id', $userId);
                }

                if ($guestIdentifier) {
                    $query->orWhere('guest_identifier', $guestIdentifier);
                }

                if (!$userId && !$guestIdentifier && $ip) {
                    $query->orWhere('ip_address', $ip);
                }
            })
            ->exists();
    }

    private function orderBaseQuery(int $companyId, ?string $dateFrom, ?string $dateTo, ?string $status): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('o.deleted_at')
            ->whereNull('oi.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->when(
                $status,
                fn($q) => $q->where('o.status', $status),
                fn($q) => $q->whereIn('o.status', OrderStatusEnum::placedValues())
            );
    }

    private function basketBaseQuery(int $companyId, ?string $dateFrom, ?string $dateTo): \Illuminate\Database\Query\Builder
    {
        return DB::table('baskets as b')
            ->leftJoin('order_items as oi', function ($join) {
                $join->on('oi.basket_id', '=', 'b.id')->whereNull('oi.deleted_at');
            })
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'b.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('b.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('b.created_at', '<=', $dateTo . ' 23:59:59'));
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
