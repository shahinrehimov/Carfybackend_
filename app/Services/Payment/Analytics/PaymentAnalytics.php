<?php

namespace App\Services\Payment\Analytics;

use App\Enum\OrderPaymentStatusEnum;
use App\Models\Order;
use Illuminate\Support\Collection;

class PaymentAnalytics
{
    public function getSuccessRates(string $period = '30 days'): Collection
    {
        return Order::query()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as successful', [OrderPaymentStatusEnum::PAID->value])
            ->selectRaw('ROUND((SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) / COUNT(*) * 100), 2) as success_rate', [OrderPaymentStatusEnum::PAID->value])
            ->where('created_at', '>=', now()->sub($period))
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderByDesc('success_rate')
            ->get();
    }

    public function getRevenueByMethod(string $period = '30 days'): Collection
    {
        return Order::query()
            ->select('payment_method')
            ->selectRaw('SUM(amount_final) as total_revenue')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('AVG(amount_final) as average_amount')
            ->selectRaw('MIN(amount_final) as min_amount')
            ->selectRaw('MAX(amount_final) as max_amount')
            ->where('payment_status', OrderPaymentStatusEnum::PAID->value)
            ->where('created_at', '>=', now()->sub($period))
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderByDesc('total_revenue')
            ->get();
    }

    public function getFailureReasons(string $period = '30 days'): Collection
    {
        return Order::query()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as failure_count')
            ->selectRaw('ROUND((COUNT(*) / (SELECT COUNT(*) FROM orders WHERE payment_status = ? AND created_at >= ?) * 100), 2) as failure_percentage', [
                OrderPaymentStatusEnum::FAILED->value,
                now()->sub($period)
            ])
            ->where('payment_status', OrderPaymentStatusEnum::FAILED->value)
            ->where('created_at', '>=', now()->sub($period))
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderByDesc('failure_count')
            ->get();
    }

    public function getPaymentMethodPopularity(string $period = '30 days'): Collection
    {
        return Order::query()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as usage_count')
            ->selectRaw('ROUND((COUNT(*) / (SELECT COUNT(*) FROM orders WHERE created_at >= ?) * 100), 2) as usage_percentage', [now()->sub($period)])
            ->where('created_at', '>=', now()->sub($period))
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->orderByDesc('usage_count')
            ->get();
    }

    public function getHourlyPaymentStats(?string $date = null): Collection
    {
        $date = $date ?? now()->toDateString();

        return Order::query()
            ->select('payment_method')
            ->selectRaw('HOUR(created_at) as hour')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(amount_final) as hourly_revenue')
            ->whereDate('created_at', $date)
            ->whereNotNull('payment_method')
            ->groupBy('payment_method', 'hour')
            ->orderBy('hour')
            ->get();
    }

    public function getAverageProcessingTime(string $period = '30 days'): Collection
    {
        return Order::query()
            ->select('payment_method')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_processing_seconds')
            ->selectRaw('MIN(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as min_processing_seconds')
            ->selectRaw('MAX(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as max_processing_seconds')
            ->where('payment_status', OrderPaymentStatusEnum::PAID->value)
            ->where('created_at', '>=', now()->sub($period))
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->get();
    }

    public function getDashboardStats(string $period = '30 days'): array
    {
        $totalOrders = Order::query()->where('created_at', '>=', now()->sub($period))->count();
        $completedOrders = Order::query()->where('payment_status', OrderPaymentStatusEnum::PAID)
            ->where('created_at', '>=', now()->sub($period))
            ->count();
        $totalRevenue = Order::query()->where('payment_status', OrderPaymentStatusEnum::PAID)
            ->where('created_at', '>=', now()->sub($period))
            ->sum('amount_final');

        return [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'success_rate' => $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 2) : 0,
            'total_revenue' => $totalRevenue,
            'average_order_value' => $completedOrders > 0 ? round($totalRevenue / $completedOrders, 2) : 0,
            'period' => $period,
        ];
    }
}
