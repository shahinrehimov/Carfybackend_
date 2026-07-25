<?php

namespace App\Http\Controllers\Webhook;

use App\Enum\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\OrderShipping;
use App\Services\WoltDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WoltWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['message' => 'ok']);
        }

        $wolt = WoltDriveService::instance();

        $payload = $wolt->verifyWebhookSignature($token);

        if ($payload === false) {
            Log::warning('Wolt webhook: invalid signature');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $type    = $payload['type'] ?? null;
        $details = $payload['details'] ?? [];
        $woltId  = $details['wolt_order_reference_id'] ?? null;

        if (!$woltId) {
            return response()->json(['message' => 'ok']);
        }

        Log::info("Wolt webhook received", ['type' => $type, 'wolt_id' => $woltId]);

        $shipping = $this->findShipping($woltId);

        if (!$shipping) {
            Log::warning('Wolt webhook: no shipping found', ['wolt_id' => $woltId]);

            return response()->json(['message' => 'ok']);
        }

        match ($type) {
            'order.pickup_started'     => $this->onPickupStarted($shipping),
            'order.dropoff_completed',
            'order.delivered'          => $this->onDelivered($shipping, $woltId),
            'order.rejected'           => $this->onRejected($shipping, $woltId, $details),
            'order.pickup_eta_updated',
            'order.dropoff_eta_updated'=> $this->onEtaUpdated($shipping, $woltId, $details),
            default                    => null,
        };

        return response()->json(['message' => 'ok']);
    }

    private function findShipping(string $woltId): ?OrderShipping
    {
        return OrderShipping::query()
            ->whereJsonContains('wolt_deliveries', [['wolt_order_reference_id' => $woltId]])
            ->first();
    }

    private function onPickupStarted(OrderShipping $shipping): void
    {
        $order = $shipping->order;

        if ($order && $order->status !== OrderStatusEnum::COURIER_ASSIGNED) {
            $order->update(['status' => OrderStatusEnum::COURIER_ASSIGNED]);
            $order->statuses()->create(['status' => OrderStatusEnum::COURIER_ASSIGNED]);
        }
    }

    private function onDelivered(OrderShipping $shipping, string $woltId): void
    {
        $this->updateDeliveryStatus($shipping, $woltId, 'delivered');

        $order = $shipping->order;

        if (!$order) {
            return;
        }

        // Check if ALL deliveries across ALL shippings for this order are delivered
        $allDelivered = $order->shippings()->get()->every(function (OrderShipping $s) {
            $deliveries = $s->wolt_deliveries ?? [];

            if (empty($deliveries)) {
                return false;
            }

            return collect($deliveries)->every(fn($d) => ($d['status'] ?? '') === 'delivered');
        });

        if ($allDelivered) {
            $order->update(['status' => OrderStatusEnum::DELIVERED]);
            $order->statuses()->create(['status' => OrderStatusEnum::DELIVERED]);
        }
    }

    private function onRejected(OrderShipping $shipping, string $woltId, array $details): void
    {
        $this->updateDeliveryStatus($shipping, $woltId, 'rejected');
        $shipping->update(['wolt_status' => 'rejected']);

        Log::error('Wolt delivery REJECTED', [
            'wolt_id'   => $woltId,
            'order_id'  => $shipping->order_id,
            'company_id'=> $shipping->company_id,
            'reason'    => $details['purchase_rejected_reason'] ?? null,
        ]);

        // Admin alert for Wolt rejection is out of scope: Admin is not a User model in this project
    }

    private function onEtaUpdated(OrderShipping $shipping, string $woltId, array $details): void
    {
        $this->updateDeliveryStatus($shipping, $woltId, null, [
            'pickup_eta'  => $details['pickup']['eta'] ?? null,
            'dropoff_eta' => $details['dropoff']['eta'] ?? null,
        ]);
    }

    private function updateDeliveryStatus(OrderShipping $shipping, string $woltId, ?string $status, array $extra = []): void
    {
        $deliveries = $shipping->wolt_deliveries ?? [];

        foreach ($deliveries as &$delivery) {
            if (($delivery['wolt_order_reference_id'] ?? '') === $woltId) {
                if ($status !== null) {
                    $delivery['status'] = $status;
                }

                foreach ($extra as $key => $value) {
                    $delivery[$key] = $value;
                }

                break;
            }
        }

        $overallStatus = $status ?? $shipping->wolt_status;
        $shipping->update([
            'wolt_deliveries' => $deliveries,
            'wolt_status'     => $overallStatus,
        ]);
    }
}
