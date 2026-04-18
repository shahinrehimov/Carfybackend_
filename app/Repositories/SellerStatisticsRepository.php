<?php

namespace App\Repositories;

use App\Models\CompanyProductVariant;
use App\Models\CompanyProductVariantView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerStatisticsRepository
{
    // ─── Order Statistics ────────────────────────────────────────────────────

    public function getOrderStats(int $companyId, array $validated): array
    {
        $period   = $validated['period'] ?? 'daily';
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $status   = $validated['status'] ?? null;

        $base = $this->orderBaseQuery($companyId, $dateFrom, $dateTo, $status);

        $summary = (clone $base)
            ->selectRaw('
                COUNT(DISTINCT o.id) as total_orders,
                COALESCE(SUM(oi.amount_final), 0) as total_revenue_cents,
                COALESCE(SUM(oi.quantity), 0) as total_quantity
            ')
            ->first();

        $chart = (clone $base)
            ->selectRaw("
                DATE_TRUNC('{$period}', o.created_at) as period_date,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(oi.amount_final), 0) as revenue_cents,
                COALESCE(SUM(oi.quantity), 0) as quantity
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
                'date'        => substr($row->period_date, 0, $period === 'monthly' ? 7 : 10),
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
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('
                cpv.id as cpv_id,
                cpv.uuid as cpv_uuid,
                COALESCE(SUM(oi.quantity), 0) as quantity_sold,
                COALESCE(SUM(oi.amount_final), 0) as revenue_cents,
                COUNT(DISTINCT oi.order_id) as order_count
            ')
            ->groupBy('cpv.id', 'cpv.uuid')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $cpvIds = $rows->pluck('cpv_id')->all();

        $cpvs = CompanyProductVariant::query()
            ->whereIn('id', $cpvIds)
            ->with(['productVariant.product', 'productVariant.media', 'media'])
            ->get()
            ->keyBy('id');

        return $rows->map(function ($row) use ($cpvs) {
            $cpv = $cpvs->get($row->cpv_id);

            return [
                'cpv'           => $this->formatCpv($cpv),
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue'       => round($row->revenue_cents / 100, 2),
                'order_count'   => (int) $row->order_count,
            ];
        });
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
            ->selectRaw('cpv.id as cpv_id, cpv.uuid as cpv_uuid, COUNT(v.id) as view_count')
            ->groupBy('cpv.id', 'cpv.uuid')
            ->orderByDesc('view_count')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $cpvIds = $rows->pluck('cpv_id')->all();

        $cpvs = CompanyProductVariant::query()
            ->whereIn('id', $cpvIds)
            ->with(['productVariant.product', 'productVariant.media', 'media'])
            ->get()
            ->keyBy('id');

        return $rows->map(fn($row) => [
            'cpv'        => $this->formatCpv($cpvs->get($row->cpv_id)),
            'view_count' => (int) $row->view_count,
        ]);
    }

    // ─── Customer Behavior ───────────────────────────────────────────────────

    public function getCustomerBehavior(int $companyId, array $validated): array
    {
        $dateFrom      = $validated['date_from'] ?? null;
        $dateTo        = $validated['date_to'] ?? null;
        $abandonedLimit = $validated['abandoned_limit'] ?? 10;

        $totalViews = DB::table('company_product_variant_views as v')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'v.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('v.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('v.created_at', '<=', $dateTo . ' 23:59:59'))
            ->count();

        $basketStats = DB::table('baskets as b')
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'b.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('b.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('b.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('COUNT(DISTINCT b.id) as total_basket_adds')
            ->first();

        $convertedBaskets = DB::table('baskets as b')
            ->join('order_items as oi', function ($join) {
                $join->on('oi.basket_id', '=', 'b.id')->whereNull('oi.deleted_at');
            })
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'b.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('b.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('b.created_at', '<=', $dateTo . ' 23:59:59'))
            ->count(DB::raw('DISTINCT b.id'));

        $totalBasketAdds = (int) ($basketStats->total_basket_adds ?? 0);

        $abandonedRows = DB::table('baskets as b')
            ->leftJoin('order_items as oi', function ($join) {
                $join->on('oi.basket_id', '=', 'b.id')->whereNull('oi.deleted_at');
            })
            ->join('company_product_variant as cpv', 'cpv.id', '=', 'b.company_product_variant_id')
            ->where('cpv.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('cpv.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('b.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('b.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('
                cpv.id as cpv_id,
                cpv.uuid as cpv_uuid,
                COUNT(DISTINCT b.id) as basket_count,
                COUNT(DISTINCT oi.id) as order_item_count
            ')
            ->groupBy('cpv.id', 'cpv.uuid')
            ->havingRaw('COUNT(DISTINCT b.id) > COUNT(DISTINCT oi.id)')
            ->orderByRaw('COUNT(DISTINCT b.id) - COUNT(DISTINCT oi.id) DESC')
            ->limit($abandonedLimit)
            ->get();

        $cpvIds = $abandonedRows->pluck('cpv_id')->all();
        $cpvs   = CompanyProductVariant::query()
            ->whereIn('id', $cpvIds)
            ->with(['productVariant.product', 'productVariant.media', 'media'])
            ->get()
            ->keyBy('id');

        $abandonedProducts = $abandonedRows->map(function ($row) use ($cpvs) {
            $basketCount    = (int) $row->basket_count;
            $orderItemCount = (int) $row->order_item_count;
            $abandonedCount = $basketCount - $orderItemCount;

            return [
                'cpv'              => $this->formatCpv($cpvs->get($row->cpv_id)),
                'basket_count'     => $basketCount,
                'order_count'      => $orderItemCount,
                'abandoned_count'  => $abandonedCount,
                'abandonment_rate' => $basketCount > 0
                    ? round($abandonedCount / $basketCount * 100, 2)
                    : 0,
            ];
        })->values();

        return [
            'overview' => [
                'total_views'           => (int) $totalViews,
                'total_basket_adds'     => $totalBasketAdds,
                'converted_baskets'     => (int) $convertedBaskets,
                'view_to_basket_rate'   => $totalViews > 0
                    ? round($totalBasketAdds / $totalViews * 100, 2)
                    : 0,
                'basket_to_order_rate'  => $totalBasketAdds > 0
                    ? round($convertedBaskets / $totalBasketAdds * 100, 2)
                    : 0,
                'view_to_order_rate'    => $totalViews > 0
                    ? round($convertedBaskets / $totalViews * 100, 2)
                    : 0,
            ],
            'abandoned_products' => $abandonedProducts,
        ];
    }

    // ─── View Tracking ───────────────────────────────────────────────────────

    public function trackView(CompanyProductVariant $cpv, \Illuminate\Http\Request $request): void
    {
        CompanyProductVariantView::create([
            'company_product_variant_id' => $cpv->id,
            'user_id'                    => $request->user()?->id,
            'guest_identifier'           => $request->header('X-Guest-Identifier'),
            'ip_address'                 => $request->ip(),
            'user_agent'                 => $request->userAgent(),
            'created_at'                 => now(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

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
            ->when($status, fn($q) => $q->where('o.status', $status));
    }

    private function formatCpv(?CompanyProductVariant $cpv): ?array
    {
        if (!$cpv) {
            return null;
        }

        $variant = $cpv->relationLoaded('productVariant') ? $cpv->productVariant : null;

        return [
            'id'         => $cpv->id,
            'uuid'       => $cpv->uuid,
            'title'      => $variant?->title,
            'main_image' => $cpv->listing_main_image,
        ];
    }
}
