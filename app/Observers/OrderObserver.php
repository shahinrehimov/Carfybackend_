<?php

namespace App\Observers;

use App\Models\Order;

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
