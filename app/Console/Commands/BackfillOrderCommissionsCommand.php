<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use App\Services\CommissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills the commission snapshot on order items placed before commissions
 * existed. New orders get their snapshot in OrderService, so this only ever
 * touches the historical backlog and is safe to re-run.
 */
class BackfillOrderCommissionsCommand extends Command
{
    protected $signature = 'app:backfill-order-commissions
        {--dry-run : Only report what would be written}
        {--percent= : Use this rate for every item instead of the resolved one}';

    protected $description = 'Write the commission snapshot on order items that do not have one yet';

    public function handle(): int
    {
        $forcedPercent = $this->option('percent');

        if ($forcedPercent !== null && !is_numeric($forcedPercent)) {
            $this->error('--percent must be a number.');

            return self::FAILURE;
        }

        $forcedPercent = $forcedPercent === null ? null : (float) $forcedPercent;

        $query = OrderItem::query()
            ->whereNull('commission_amount')
            ->with(['companyProductVariant' => fn($q) => $q->withTrashed()])
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Every order item already has a commission snapshot.');

            return self::SUCCESS;
        }

        $commission = CommissionService::instance();

        if ($this->option('dry-run')) {
            $this->info("{$total} order item(s) would be updated. First 20:");

            (clone $query)->limit(20)->get()->each(function (OrderItem $item) use ($commission, $forcedPercent) {
                $percent = $forcedPercent ?? $commission->percentFor($item->companyProductVariant);
                $amount  = $commission->applyPercent((float) $item->amount_final, $percent);

                $this->line("- item #{$item->id}: {$item->amount_final} × {$percent}% => {$amount}");
            });

            return self::SUCCESS;
        }

        $this->info("Updating {$total} order item(s).");

        $updated = 0;
        $bar     = $this->output->createProgressBar($total);

        // Each pass removes rows from the filtered set, so re-query instead of
        // paginating with chunkById.
        while (true) {
            $items = (clone $query)->limit(500)->get();

            if ($items->isEmpty()) {
                break;
            }

            foreach ($items as $item) {
                $percent = $forcedPercent ?? $commission->percentFor($item->companyProductVariant);
                $amount  = $commission->applyPercent((float) $item->amount_final, $percent);

                DB::table('order_items')
                    ->where('id', $item->id)
                    ->update([
                        'commission_percent' => $percent,
                        'commission_amount'  => (int) round($amount * 100),
                    ]);

                $updated++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Done. {$updated} item(s) updated.");

        return self::SUCCESS;
    }
}
