<?php

namespace App\Repositories;

use App\Enum\OrderStatusEnum;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ControlStatisticsRepository
{
    // ─── Order Statistics ────────────────────────────────────────────────────

    public function getOrderStats(array $validated): array
    {
        $period    = $this->pgPeriod($validated['period'] ?? 'daily');
        $dateFrom  = $validated['date_from'] ?? null;
        $dateTo    = $validated['date_to'] ?? null;
        $status    = $validated['status'] ?? null;
        $companyId = $validated['company_id'] ?? null;

        $base = $this->orderBaseQuery($dateFrom, $dateTo, $status, $companyId);

        $summary = (clone $base)
            ->selectRaw('
                COUNT(DISTINCT o.id)          as total_orders,
                COALESCE(SUM(oi.amount_final), 0) as total_revenue_cents,
                COALESCE(SUM(oi.quantity), 0)     as total_quantity
            ')
            ->first();

        $chart = (clone $base)
            ->selectRaw("
                DATE_TRUNC('{$period}', o.created_at) as period_date,
                COUNT(DISTINCT o.id)                   as order_count,
                COALESCE(SUM(oi.amount_final), 0)      as revenue_cents,
                COALESCE(SUM(oi.quantity), 0)          as quantity
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
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->selectRaw('
                c.id                              as company_id,
                c.uuid                            as company_uuid,
                COALESCE(SUM(oi.quantity), 0)     as quantity_sold,
                COALESCE(SUM(oi.amount_final), 0) as revenue_cents,
                COUNT(DISTINCT o.id)              as order_count
            ')
            ->groupBy('c.id', 'c.uuid')
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get();

        $companyIds = $rows->pluck('company_id')->all();

        $companies = Company::query()
            ->whereIn('id', $companyIds)
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

        $cpvIds = $selling->pluck('cpv_id')
            ->merge($viewed->pluck('cpv_id'))
            ->unique()
            ->values()
            ->all();

        $cpvs = \App\Models\CompanyProductVariant::query()
            ->whereIn('id', $cpvIds)
            ->with(['productVariant.product', 'productVariant.media', 'media'])
            ->get()
            ->keyBy('id');

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

        // Users active via last Sanctum token creation in period
        $activeViaLogin = DB::table('users as u')
            ->join(
                DB::raw('(SELECT tokenable_id, MAX(created_at) as last_login FROM personal_access_tokens WHERE tokenable_type = \'App\\\Models\\\User\' GROUP BY tokenable_id) as t'),
                't.tokenable_id', '=', 'u.id'
            )
            ->whereNull('u.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('t.last_login', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('t.last_login', '<=', $dateTo . ' 23:59:59'))
            ->count();

        $activeViaOrder = DB::table('users as u')
            ->join('orders as o', 'o.user_id', '=', 'u.id')
            ->whereNull('u.deleted_at')
            ->whereNull('o.deleted_at')
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'))
            ->distinct('u.id')
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

    public function getCancellations(array $validated): array
    {
        $dateFrom  = $validated['date_from'] ?? null;
        $dateTo    = $validated['date_to'] ?? null;
        $companyId = $validated['company_id'] ?? null;
        $limit     = $validated['limit'] ?? 10;

        $base = DB::table('orders as o')
            ->whereNull('o.deleted_at')
            ->where('o.status', OrderStatusEnum::CANCELED->value)
            ->when($dateFrom, fn($q) => $q->where('o.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('o.created_at', '<=', $dateTo . ' 23:59:59'));

        if ($companyId) {
            $base->join('order_items as oi', 'oi.order_id', '=', 'o.id')
                ->join('company_product_variant as cpv', 'cpv.id', '=', 'oi.company_product_variant_id')
                ->whereNull('oi.deleted_at')
                ->whereNull('cpv.deleted_at')
                ->where('cpv.company_id', $companyId);
        }

        $total = (clone $base)->distinct()->count('o.id');

        $byActor = (clone $base)
            ->selectRaw('o.cancelled_by_type, COUNT(DISTINCT o.id) as count')
            ->whereNotNull('o.cancelled_by_type')
            ->groupBy('o.cancelled_by_type')
            ->get()
            ->map(fn($r) => [
                'actor' => class_basename($r->cancelled_by_type),
                'count' => (int) $r->count,
            ]);

        $topReasons = (clone $base)
            ->selectRaw('o.cancel_reason, COUNT(DISTINCT o.id) as count')
            ->whereNotNull('o.cancel_reason')
            ->groupBy('o.cancel_reason')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'reason' => $r->cancel_reason,
                'count'  => (int) $r->count,
            ]);

        return [
            'total_cancelled' => $total,
            'by_actor'        => $byActor->values(),
            'top_reasons'     => $topReasons->values(),
        ];
    }

    // ─── Commission Statistics ────────────────────────────────────────────────

    public function getCommissions(array $validated): array
    {
        // TODO: commission logic to be defined — returning zero values for now
        return [
            'total_commission' => 0,
            'chart'            => [],
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function pgPeriod(string $period): string
    {
        return match($period) {
            'weekly'  => 'week',
            'monthly' => 'month',
            default   => 'day',
        };
    }

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
            ->when($status, fn($q) => $q->where('o.status', $status));
    }

    private function formatCompany(?Company $company): ?array
    {
        if (!$company) {
            return null;
        }

        return [
            'id'   => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->title,
        ];
    }

    private function formatCpv(?\App\Models\CompanyProductVariant $cpv): ?array
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
