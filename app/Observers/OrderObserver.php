<?php

namespace App\Observers;

use App\Enum\OrderStatusEnum;
use App\Models\Order;
use App\Services\NotificationDispatchService;
use App\Services\ReferralService;

class OrderObserver
{
    /**
     * Handle the Company "created" event.
     */
    public function created(Order $order): void
    {
        $order->statuses()
            ->create([
                'status' => $order->status,
            ]);

        if ($order->payment_status) {
            $order->paymentStatuses()
                ->create([
                    'payment_status' => $order->payment_status,
                ]);
        }

        NotificationDispatchService::instance()->orderPlaced($order);
    }

    /**
     * Handle the Company "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->isDirty('status')) {
            $order->statuses()
                ->create([
                    'status' => $order->status,
                ]);

            // Order reaching PROCESSING means it is confirmed: online payment was
            // marked PAID, or an instant method like cash placed it directly. Items
            // exist by now, so alert the seller(s) exactly once here.
            if ($order->status === OrderStatusEnum::PROCESSING) {
                NotificationDispatchService::instance()->newOrderReceived($order);
            }

            if ($order->status === OrderStatusEnum::CANCELED) {
                NotificationDispatchService::instance()->orderCancelled($order);
                ReferralService::instance()->handleOrderCancelled($order);
            } elseif ($order->status === OrderStatusEnum::DELIVERED) {
                NotificationDispatchService::instance()->orderDelivered($order);
                ReferralService::instance()->handleOrderDelivered($order);
            } else {
                NotificationDispatchService::instance()->orderStatusChanged($order);
            }
        }

        if ($order->isDirty('payment_status') && $order->payment_status) {
            $order->paymentStatuses()
                ->create([
                    'payment_status' => $order->payment_status,
                ]);
        }
    }

    /**
     * Handle the Company "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Company "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Company "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
