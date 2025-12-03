<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentMethodFactory;
use App\Services\Payment\PaymentMethodDiscovery;
use App\Services\Payment\Analytics\PaymentAnalytics;
use Illuminate\Console\Command;

class PaymentCommand extends Command
{
    protected $signature = 'payment {action : debug|discovery|clear-cache|analytics} {--period=30 days}';
    protected $description = 'Payment system management commands';

    public function handle(): void
    {
        switch ($this->argument('action')) {
            case 'debug':
                $this->debugPaymentMethods();
                break;
            case 'discovery':
                $this->runDiscovery();
                break;
            case 'clear-cache':
                $this->clearCache();
                break;
            case 'analytics':
                $this->showAnalytics();
                break;
            default:
                $this->error('Invalid action. Available actions: debug, discovery, clear-cache, analytics');
        }
    }

    private function debugPaymentMethods(): void
    {
        $this->info('Payment Methods Debug Information:');
        $this->line('');

        $debug = PaymentMethodFactory::debug();

        $tableData = [];
        foreach ($debug as $method => $info) {
            $status = $info['enabled'] ? '✅ ENABLED' : '❌ DISABLED';

            if (isset($info['error'])) {
                $status = '⚠️  ERROR';
            }

            $tableData[] = [
                $method,
                $info['class'],
                $info['priority'] ?? 'N/A',
                    $info['auto_discovered'] ?? false ? 'Auto-discovered' : 'Config',
                $status,
                $info['display_name'] ?? 'N/A'
            ];
        }

        $this->table(
            ['Method', 'Class', 'Priority', 'Source', 'Status', 'Display Name'],
            $tableData
        );

        // Show errors
        foreach ($debug as $method => $info) {
            if (isset($info['error'])) {
                $this->error("❌ {$method}: {$info['error']}");
            }
        }

        $enabledCount = count(array_filter($debug, fn($info) => $info['enabled']));
        $totalCount = count($debug);

        $this->info("Summary: {$enabledCount} enabled out of {$totalCount} configured methods");
    }

    private function runDiscovery(): void
    {
        $this->info('Running payment method auto-discovery...');

        $discovery = app(PaymentMethodDiscovery::class);
        $discovery->clearCache();
        $discovered = $discovery->discover();

        $this->info('Auto-discovery completed!');
        $this->info('Found ' . count($discovered) . ' payment methods');

        if (!empty($discovered)) {
            $this->line('');
            foreach ($discovered as $name => $config) {
                $this->line("📦 {$name}: {$config['class']}");
            }
        }

        PaymentMethodFactory::clearCache();
        $this->info('Cache cleared and methods reloaded.');
    }

    private function clearCache(): void
    {
        PaymentMethodFactory::clearCache();
        app(PaymentMethodDiscovery::class)->clearCache();

        $this->info('✅ Payment system cache cleared!');
    }

    private function showAnalytics(): void
    {
        $period = $this->option('period');
        $analytics = app(PaymentAnalytics::class);

        $this->info("Payment Analytics for last {$period}");
        $this->line('');

        // Dashboard stats
        $dashboardStats = $analytics->getDashboardStats($period);
        $this->info('📊 Dashboard Overview:');
        $this->line("   Total Orders: {$dashboardStats['total_orders']}");
        $this->line("   Completed Orders: {$dashboardStats['completed_orders']}");
        $this->line("   Success Rate: {$dashboardStats['success_rate']}%");
        $this->line("   Total Revenue: " . number_format($dashboardStats['total_revenue'] / 100, 2) . " AZN");
        $this->line("   Average Order Value: " . number_format($dashboardStats['average_order_value'] / 100, 2) . " AZN");
        $this->line('');

        // Success rates
        $successRates = $analytics->getSuccessRates($period);
        if ($successRates->isNotEmpty()) {
            $this->info('✅ Success Rates by Payment Method:');
            $this->table(
                ['Payment Method', 'Total', 'Successful', 'Success Rate'],
                $successRates->map(fn($item) => [
                    $item->payment_method,
                    $item->total,
                    $item->successful,
                    $item->success_rate . '%'
                ])->toArray()
            );
        }

        // Revenue by method
        $revenue = $analytics->getRevenueByMethod($period);
        if ($revenue->isNotEmpty()) {
            $this->info('💰 Revenue by Payment Method:');
            $this->table(
                ['Payment Method', 'Revenue (AZN)', 'Transactions', 'Avg Amount (AZN)'],
                $revenue->map(fn($item) => [
                    $item->payment_method,
                    number_format($item->total_revenue / 100, 2),
                    $item->transaction_count,
                    number_format($item->average_amount / 100, 2)
                ])->toArray()
            );
        }

        // Popularity
        $popularity = $analytics->getPaymentMethodPopularity($period);
        if ($popularity->isNotEmpty()) {
            $this->info('📈 Payment Method Popularity:');
            $this->table(
                ['Payment Method', 'Usage Count', 'Usage %'],
                $popularity->map(fn($item) => [
                    $item->payment_method,
                    $item->usage_count,
                    $item->usage_percentage . '%'
                ])->toArray()
            );
        }
    }
}
