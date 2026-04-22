<?php

namespace App\Console\Commands;

use App\Enum\OrderItemStatusEnum;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\Setting;
use App\Services\LoggerService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSellerOrderTimeout extends Command
{
    protected $signature = 'orders:check-seller-timeout';
    protected $description = 'Auto-cancel order items where seller did not respond within the configured timeout';

    public function handle(): void
    {
        $minutes = (int) Setting::query()->where('key', 'order_auto_cancel_minutes')->value('value') ?: 60;
        $cutoff = Carbon::now()->subMinutes($minutes);

        $timedOutItems = OrderItem::query()
            ->where('status', OrderItemStatusEnum::PENDING)
            ->where('created_at', '<=', $cutoff)
            ->with(['order', 'companyProductVariant'])
            ->get();

        if ($timedOutItems->isEmpty()) {
            return;
        }

        LoggerService::instance()->log(
            "Auto-canceling {$timedOutItems->count()} order items (seller timeout: {$minutes} min)",
            [],
            true,
            'warning'
        );

        foreach ($timedOutItems as $item) {
            $item->update([
                'status' => OrderItemStatusEnum::CANCELED,
                'cancel_reason' => 'Magaza geri donus etmedi',
            ]);

            OrderItemStatus::create([
                'order_item_id' => $item->id,
                'status' => OrderItemStatusEnum::CANCELED,
            ]);

            LoggerService::instance()->log(
                "Auto-canceled order item #{$item->id} (order #{$item->order_id})",
                ['item_id' => $item->id, 'order_id' => $item->order_id],
                true,
                'warning'
            );

            OrderService::instance()->processItemCancellation($item);
        }

        $this->info("Processed {$timedOutItems->count()} timed-out items.");
    }
}
