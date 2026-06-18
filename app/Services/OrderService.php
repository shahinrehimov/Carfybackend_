<?php

namespace App\Services;

use App\DTOs\Payment\PaymentResult;
use App\Enum\OrderItemStatusEnum;
use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentLogActionEnum;
use App\Models\Order;
use App\Services\ReferralService;
use App\Models\OrderItem;
use App\Models\OrderItemStatus;
use App\Models\OrderShipping;
use App\Models\OrderStatus;
use App\Models\CompanyProductVariant;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoggerService;
use App\Services\NotificationDispatchService;
use App\Services\SettingService;
use App\Services\Payment\PaymentService;
use App\Services\WoltDriveService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrderService
{
    private static ?OrderService $instance = null;
    private ?User $user = null;

    private function __construct()
    {

    }

    public static function instance(): OrderService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): User
    {
        if ($this->user === null) {
            if (!UserService::instance()->checkUser()) {
                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('User not found')
                        ->getLang('user_not_found')
                );
            }

            $this->user = UserService::instance()->getUser();
        }

        return $this->user;
    }

    public function checkoutPreview(int $addressId): array
    {
        if (!config('wolt.enabled')) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Delivery service is not available. Please contact us.')
                    ->getLang('delivery_service_unavailable')
            );
        }

        $user = $this->getUser();
        $productVariants = BasketService::instance()->loadProducts($user);

        if ($productVariants->isEmpty()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Your basket is empty')
                    ->getLang('basket_empty')
            );
        }

        $address = $user->addresses()->findOrFail($addressId);

        $sellers = [];

        foreach ($productVariants as $productVariant) {
            $cpvId     = $productVariant->pivot->company_product_variant_id ?? null;
            $cpv       = $productVariant->seller_listing ?? null;
            $companyId = $cpv?->company_id;

            if (!$companyId) {
                continue;
            }

            if (!isset($sellers[$companyId])) {
                $sellers[$companyId] = [
                    'company_id'   => $companyId,
                    'store_code'   => $cpv->company?->store_code ?? '',
                    '_company'     => $cpv->company,
                    'items'        => [],
                    '_wolt_items'  => [],
                    'subtotal'     => 0,
                ];
            }

            $sellers[$companyId]['items'][] = [
                'quantity' => $productVariant->basket_quantity,
                'price'    => round($productVariant->price_final_in_basket, 2),
                '_cpv'     => $cpv,
            ];

            $sellers[$companyId]['_wolt_items'][] = [
                'weight_gram'   => $cpv->weight_gram ?? 0,
                'size_category' => $cpv->size_category,
                'quantity'      => $productVariant->basket_quantity,
                'amount_cents'  => (int) round($productVariant->price_final_in_basket * 100),
            ];

            $sellers[$companyId]['subtotal'] += $productVariant->price_final_in_basket;
        }

        $wolt = WoltDriveService::instance();

        $batchSpecs = [];
        foreach ($sellers as $companyId => $seller) {
            $batchSpecs[$companyId] = [
                'company'  => $seller['_company'],
                'dropLat'  => (float) $address->latitude,
                'dropLon'  => (float) $address->longitude,
                'dropAddr' => $address->address_line_1,
                'items'    => $seller['_wolt_items'],
            ];
        }

        $fees = $wolt->getDeliveryFeesBatch($batchSpecs);

        $totalShipping = 0;
        $subtotal      = 0;

        foreach ($sellers as $companyId => &$seller) {
            $seller['subtotal'] = round($seller['subtotal'], 2);
            $subtotal += $seller['subtotal'];

            $totalWeight             = $wolt->totalWeightGram($seller['_wolt_items']);
            $seller['weight_warning'] = $totalWeight > config('wolt.max_weight_gram');

            $fee = $fees[$companyId];

            $seller['shipping']             = round($fee['amount_cents'] / 100, 2);
            $seller['shipping_eta_minutes'] = $fee['eta_minutes'];
            $seller['shipping_is_estimate'] = $fee['is_estimate'];
            $seller['is_sedan']             = $fee['is_sedan'];

            unset($seller['_company'], $seller['_wolt_items']);

            $totalShipping += $seller['shipping'];
        }

        // Referral perks preview
        $referralRepo    = ReferralService::instance()->repository();
        $perk            = $referralRepo->getOrCreatePerks($user);
        $attribution     = $referralRepo->getAttributionForReferred($user);
        $hasPerkEligibility = $attribution !== null;

        $freeDeliveryApplied = $hasPerkEligibility && $perk->hasFreeDelivery();
        $discountApplied     = $hasPerkEligibility && $perk->hasDiscount();

        $previewShipping    = $freeDeliveryApplied ? 0 : round($totalShipping, 2);
        $previewDiscount    = $discountApplied ? round($subtotal * 1 / 100, 2) : 0;
        $previewSubtotal    = round($subtotal - $previewDiscount, 2);

        $balance         = $referralRepo->getOrCreateBalance($user->id);
        $settings        = SettingService::instance()->get();
        $minPlatformSpend = (int) ($settings->referral_min_platform_spend ?? 2000);
        $canUseBalance   = $balance->available_balance >= $minPlatformSpend;

        return [
            'sellers'        => array_values($sellers),
            'subtotal'       => round($subtotal, 2),
            'total_shipping' => round($totalShipping, 2),
            'grand_total'    => round($subtotal + $totalShipping, 2),
            'referral'       => [
                'free_delivery_applied'     => $freeDeliveryApplied,
                'discount_applied'          => $discountApplied,
                'discount_amount'           => $previewDiscount,
                'subtotal_after_discount'   => $previewSubtotal,
                'shipping_after_perk'       => $previewShipping,
                'grand_total_after_perks'   => round($previewSubtotal + $previewShipping, 2),
                'can_use_balance'           => $canUseBalance,
                'available_balance'         => round($balance->available_balance / 100, 2),
                'free_delivery_remaining'   => $perk->remainingFreeDeliveries(),
                'discount_orders_remaining' => $perk->remainingDiscountOrders(),
            ],
        ];
    }

    public function checkout(array $validated): PaymentResult
    {
        if (!config('wolt.enabled')) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Delivery service is not available. Please contact us.')
                    ->getLang('delivery_service_unavailable')
            );
        }

        $user = $this->getUser();

        $productVariants = BasketService::instance()->loadProducts($user);

        if ($productVariants->isEmpty()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Your basket is empty')
                    ->getLang('basket_empty')
            );
        }

        $address = $user->addresses()->where('id', $validated['address_id'])->first();

        if (!$address) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Selected address not found!')
                    ->getLang('selected_address_not_found')
            );
        }

        // Calculate Wolt fees per company BEFORE the transaction (external HTTP call)
        $wolt = WoltDriveService::instance();
        $woltFeeSpecs = [];

        foreach ($productVariants as $productVariant) {
            $cpv       = $productVariant->seller_listing ?? null;
            $companyId = $cpv?->company_id;

            if (!$companyId || !$cpv->company) {
                continue;
            }

            if (!isset($woltFeeSpecs[$companyId])) {
                $woltFeeSpecs[$companyId] = [
                    'company'  => $cpv->company,
                    'dropLat'  => (float) $address->latitude,
                    'dropLon'  => (float) $address->longitude,
                    'dropAddr' => $address->address_line_1,
                    'items'    => [],
                ];
            }

            $woltFeeSpecs[$companyId]['items'][] = [
                'weight_gram'   => $cpv->weight_gram ?? 0,
                'size_category' => $cpv->size_category,
                'quantity'      => $productVariant->basket_quantity,
                'amount_cents'  => (int) round($productVariant->price_final_in_basket * 100),
            ];
        }

        $woltFees = $wolt->getDeliveryFeesBatch($woltFeeSpecs);

        /** @var Order $order */
        $order = DB::transaction(function () use ($user, $validated, $productVariants, $address, $woltFees) {
            $items = [];
            $shippingPerCompany = [];

            // Batch load all CPVs for stock validation (single query)
            $cpvIds = $productVariants
                ->map(fn($pv) => $pv->pivot->company_product_variant_id ?? null)
                ->filter()
                ->unique()
                ->values();

            $cpvs = CompanyProductVariant::query()
                ->whereIn('id', $cpvIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($productVariants as $productVariant) {
                /** @var ProductVariant $productVariant */
                $cpvId = $productVariant->pivot->company_product_variant_id ?? null;

                if ($cpvId) {
                    $cpv = $cpvs->get($cpvId);

                    if (!$cpv || !$cpv->is_active) {
                        throw new BadRequestHttpException(
                            LangService::instance()
                                ->setDefault('Product is no longer available: @product')
                                ->getLang('product_no_longer_available', [
                                    '@product' => $productVariant->title,
                                ])
                        );
                    }

                    if ($cpv->getRawOriginal('stock') < $productVariant->basket_quantity) {
                        throw new BadRequestHttpException(
                            LangService::instance()
                                ->setDefault('Insufficient stock for product: @product. Available: @stock')
                                ->getLang('insufficient_stock_for_product', [
                                    '@product' => $productVariant->title,
                                    '@stock' => $cpv->stock,
                                ])
                        );
                    }

                    // Decrement stock atomically
                    $cpv->decrement('stock', $productVariant->basket_quantity);

                    // Collect per-company shipping from Wolt fee (one entry per unique company)
                    if ($cpv->company_id && !isset($shippingPerCompany[$cpv->company_id])) {
                        $shippingPerCompany[$cpv->company_id] = round($woltFees[$cpv->company_id]['amount_cents'] / 100, 2);
                    }
                }

                $items[] = [
                    'basket_id' => $productVariant->pivot->id,
                    'product_variant_id' => $productVariant->id,
                    'product_id' => $productVariant->product_id,
                    'company_product_variant_id' => $cpvId,
                    'amount' => $productVariant->price_in_basket,
                    'amount_final' => $productVariant->price_final_in_basket,
                    'is_set' => $productVariant->isSet(),
                    'quantity' => $productVariant->basket_quantity,
                    'sets' => $productVariant->pivot?->sets ?? [],
                    'status' => OrderItemStatusEnum::PENDING,
                ];
            }

            $basketData    = BasketService::instance()->calculateBasketData($productVariants);
            $totalShipping = array_sum($shippingPerCompany);

            // --- Referral perks for referred users (free delivery + 1% discount) ---
            $referralRepo        = ReferralService::instance()->repository();
            $referralPerk        = $referralRepo->getOrCreatePerks($user);
            $referralAttribution = $referralRepo->getAttributionForReferred($user);
            $hasPerkEligibility  = $referralAttribution !== null;

            $referralProductDiscount = 0;
            $freeDeliveryApplied     = false;
            $discountApplied         = false;

            if ($hasPerkEligibility) {
                if ($referralPerk->hasFreeDelivery()) {
                    $totalShipping       = 0;
                    $freeDeliveryApplied = $referralRepo->useFreeDeliveryPerk($user);
                }

                if ($referralPerk->hasDiscount()) {
                    $subtotalCents           = (int) round($basketData['total_price_final_in_basket'] * 100);
                    $referralProductDiscount = (int) round($subtotalCents * 1 / 100);
                    $discountApplied         = $referralRepo->useDiscountPerk($user);
                }
            }

            $subtotalAfterReferral = $basketData['total_price_final_in_basket'] - ($referralProductDiscount / 100);
            $amountFinal           = $subtotalAfterReferral + $totalShipping;

            // --- Referral balance spending (user opt-in) ---
            $useReferralBalance   = (bool) ($validated['use_referral_balance'] ?? false);
            $referralBalanceUsed  = 0;
            $settings             = SettingService::instance()->get();
            $minPlatformSpend     = (int) ($settings->referral_min_platform_spend ?? 2000);
            $referralBalance      = $referralRepo->getOrCreateBalance($user->id);

            if ($useReferralBalance && $referralBalance->available_balance >= $minPlatformSpend) {
                $referralBalanceUsed = min(
                    $referralBalance->available_balance,
                    (int) round($amountFinal * 100)
                );
                $amountFinal = max(0, $amountFinal - $referralBalanceUsed / 100);
            }

            /** @var Order $order */
            $order = $user->orders()->create([
                'status' => OrderStatusEnum::PENDING,
                'payment_status' => OrderPaymentStatusEnum::PENDING,
                'payment_method' => $validated['payment_method'],
                'amount' => $basketData['total_price_in_basket'],
                'amount_final' => $amountFinal,
                'total_discount_in_basket' => $basketData['total_discount_in_basket'],
                'shipping' => $totalShipping,
                'total_quantity' => $basketData['total_basket_quantity'],
                'address_type_id' => $address->address_type_id,
                'city_id' => $address->city_id,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'phone' => $address->phone,
                'referral_free_delivery_applied' => $freeDeliveryApplied,
                'referral_discount_applied' => $discountApplied,
            ]);

            $order->items()->createMany($items);

            // Create per-seller shipping records for reporting and refund tracking
            foreach ($shippingPerCompany as $companyId => $shippingAmount) {
                $order->shippings()->create([
                    'company_id' => $companyId,
                    'amount' => $shippingAmount,
                ]);
            }

            // Basket is NOT cleared here. It is cleared only once payment is
            // confirmed (see PaymentService::processPayment for instant methods
            // like cash, and OrderService::pay() for redirect/callback methods).

            if ($referralBalanceUsed > 0) {
                $referralRepo->spendOnPlatform($user, $referralBalanceUsed, $order->id);
            }

            // Seller/admin alerts handled by OrderObserver via NotificationDispatchService::newOrderReceived()
            // (CompanyAdmin and Admin are not User models — separate mechanism needed)

            return $order;
        });

        $paymentService = app(PaymentService::class);

        return $paymentService->processPayment($order);
    }

    public function processCallback(string $paymentMethodName, array $data): PaymentResult
    {
        $paymentService = app(PaymentService::class);

        $result = $paymentService->processCallback($paymentMethodName, $data);

        $order = $result->order;

        if ($result->success) {
            if (!$result->isRefund) {
                if ($order !== null && $order->status === OrderStatusEnum::PAYMENT_PENDING && $order->payment_status === OrderPaymentStatusEnum::PENDING) {
                    $this->pay($order, OrderPaymentStatusEnum::PAID, $result->transactionId);
                    ReferralService::instance()->handlePaymentCard($order, $result->metadata);
                }
            }
        } else if (!$result->isPending) {
            if ($order !== null && $order->status === OrderStatusEnum::PAYMENT_PENDING && $order->payment_status === OrderPaymentStatusEnum::PENDING) {
                $this->pay($order, OrderPaymentStatusEnum::FAILED, $result->transactionId);
            }
        }

        return $result;
    }

    public function updatePaymentStatus(Order $order): PaymentResult
    {
        $paymentService = app(PaymentService::class);

        $result = $paymentService->checkStatus($order);

        if ($result->success) {
            if (!$result->isRefund) {
                if ($order->status === OrderStatusEnum::PAYMENT_PENDING && $order->payment_status === OrderPaymentStatusEnum::PENDING) {
                    $this->pay($order, OrderPaymentStatusEnum::PAID, $result->transactionId);
                    ReferralService::instance()->handlePaymentCard($order, $result->metadata);
                }
            }
        } else if (!$result->isPending) {
            if ($order->status === OrderStatusEnum::PAYMENT_PENDING && $order->payment_status === OrderPaymentStatusEnum::PENDING) {
                $this->pay($order, OrderPaymentStatusEnum::FAILED, $result->transactionId);
            }
        }

        return $result;
    }

    public function processItemCancellation(OrderItem $item): void
    {
        $order = $item->order;

        if (!$order) {
            return;
        }

        // A failed/canceled order already released its stock via restoreStock();
        // skip it here to avoid restoring the same units twice.
        if (in_array($order->status, [OrderStatusEnum::FAILED, OrderStatusEnum::CANCELED], true)) {
            return;
        }

        // Release the canceled item's reserved stock (applies to paid and cash orders alike).
        $this->restoreStockForItem($item);

        $itemAmount = (int) $item->getRawOriginal('amount_final');

        // Original product subtotal = sum of every item's amount_final. Items are
        // not deleted on cancel (status only), so this still reflects the basket.
        $productSubtotal = (int) $order->items->sum(
            fn($orderItem) => (int) $orderItem->getRawOriginal('amount_final')
        );

        // Return this item's share of any referral balance the buyer spent on the
        // order. Balance is consumed at checkout for every payment method (card and
        // cash alike), so it is returned here regardless of payment status — the
        // cash/card gateway refund below only covers the cash-paid portion.
        ReferralService::instance()->handleItemBalanceRefund($order, $itemAmount, $productSubtotal);

        // Nothing left to refund (in cash) for unpaid (e.g. cash) orders.
        if ($order->payment_status !== OrderPaymentStatusEnum::PAID) {
            return;
        }

        // Refund the item's cash-paid amount, adjusted for order-level reductions
        // that are NOT baked into the item's amount_final (referral 1% discount,
        // referral balance spent). These apply to the whole order, so refunding the
        // raw item amount would hand the discount/balance back as cash (over-refund).
        // What the customer actually paid in cash for products = order total minus
        // shipping; amount_final already has every order-level reduction applied.
        $paidForProducts = max(
            0,
            (int) $order->getRawOriginal('amount_final') - (int) $order->getRawOriginal('shipping')
        );

        $refundAmount = ($productSubtotal > 0 && $paidForProducts < $productSubtotal)
            ? (int) round($itemAmount * $paidForProducts / $productSubtotal)
            : $itemAmount;

        // Check if all items for this company are now canceled → also refund shipping
        $companyId = $item->companyProductVariant?->company_id;

        if ($companyId) {
            $hasActiveItemsForCompany = $order->items()
                ->where('id', '!=', $item->id)
                ->whereHas('companyProductVariant', fn($q) => $q->where('company_id', $companyId))
                ->where('status', '!=', OrderItemStatusEnum::CANCELED)
                ->exists();

            if (!$hasActiveItemsForCompany) {
                $shipping = $order->shippings()->where('company_id', $companyId)->whereNull('refunded_at')->first();
                if ($shipping) {
                    $refundAmount += $shipping->getRawOriginal('amount');
                    $shipping->update(['refunded_at' => Carbon::now()]);
                    $this->cancelWoltDeliveries($shipping, "Bütün məhsullar ləğv edildi");
                }
            }
        }

        if ($refundAmount <= 0) {
            return;
        }

        $paymentService = app(PaymentService::class);

        $result = $paymentService->refund(
            $order,
            $refundAmount,
            "Item #{$item->id} canceled: {$item->cancel_reason}"
        );

        if ($result->success) {
            $allItemsCanceled = $order->items()
                ->where('id', '!=', $item->id)
                ->where('status', '!=', OrderItemStatusEnum::CANCELED)
                ->doesntExist();

            $order->update([
                'refund_at' => Carbon::now(),
                'payment_status' => $allItemsCanceled
                    ? OrderPaymentStatusEnum::REFUNDED
                    : OrderPaymentStatusEnum::PARTIALLY_REFUNDED,
            ]);

            LoggerService::instance()->log(
                "Partial refund processed for item #{$item->id}, amount: {$refundAmount} cents",
                ['item_id' => $item->id, 'order_id' => $order->id, 'amount' => $refundAmount],
                true,
                'info'
            );

            $order->paymentLogs()->create([
                'payment_method' => $order->payment_method,
                'action' => PaymentLogActionEnum::PARTIAL_REFUND,
                'transaction_id' => $result->transactionId,
                'status' => 'success',
                'order_status' => $order->status->value,
                'request' => ['item_id' => $item->id, 'amount' => $refundAmount],
                'response' => $result->metadata ?? [],
            ]);

            NotificationDispatchService::instance()->orderItemCancelled($item);
            // Referral earning is based on product subtotal only — deduct the item's
            // product amount, excluding any shipping included in $refundAmount.
            ReferralService::instance()->handlePartialRefund($order, (int) $item->getRawOriginal('amount_final'));
        } else {
            LoggerService::instance()->log(
                "Partial refund FAILED for item #{$item->id}: {$result->message}",
                ['item_id' => $item->id, 'order_id' => $order->id, 'amount' => $refundAmount],
                true,
                'error'
            );
        }
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        $blockedOrderStatus = in_array($order->status, [
            OrderStatusEnum::RETURNED,
            OrderStatusEnum::CANCELED,
            OrderStatusEnum::DELIVERED,
        ]);

        $alreadyRefunded = in_array($order->payment_status, [
            OrderPaymentStatusEnum::REFUNDED,
            OrderPaymentStatusEnum::PARTIALLY_REFUNDED,
        ]);

        if ($order->payment_status !== OrderPaymentStatusEnum::PAID || $blockedOrderStatus || $alreadyRefunded) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Order cannot be refund')
                    ->getLang('order_cannot_be_refund')
            );
        }

        $paymentService = app(PaymentService::class);

        $result = $paymentService->refund($order, $amount, $comment);

        if ($result->success) {
            $order->update([
                'status' => OrderStatusEnum::RETURNED,
                'payment_status' => $result->amount >= $order->getRawOriginal('amount_final') ? OrderPaymentStatusEnum::REFUNDED : OrderPaymentStatusEnum::PARTIALLY_REFUNDED,
                'refund_at' => Carbon::now(),
            ]);

            $order->shippings()->get()->each(function ($shipping) {
                $this->cancelWoltDeliveries($shipping, "Sifariş geri qaytarıldı");
            });
        }

        $order->paymentLogs()->create([
            'payment_method' => $order->payment_method,
            'action' => PaymentLogActionEnum::REFUND,
            'transaction_id' => $result->transactionId,
            'status' => $result->success ? 'success' : 'failed',
            'order_status' => $order->status->value,
            'request' => ['amount' => $amount, 'comment' => $comment],
            'response' => $result->metadata ?? [],
        ]);

        return $result;
    }

    private function cancelWoltDeliveries(\App\Models\OrderShipping $shipping, string $reason): void
    {
        $deliveries = $shipping->wolt_deliveries ?? [];

        if (empty($deliveries)) {
            return;
        }

        $wolt = WoltDriveService::instance();

        foreach ($deliveries as &$delivery) {
            $woltId = $delivery['wolt_order_reference_id'] ?? null;
            $status = $delivery['status'] ?? null;

            if ($woltId && !in_array($status, ['delivered', 'canceled', 'rejected'])) {
                $wolt->cancelDelivery($woltId, $reason);
                $delivery['status'] = 'canceled';
            }
        }

        $shipping->update([
            'wolt_deliveries' => $deliveries,
            'wolt_status'     => 'canceled',
        ]);
    }

    public function load(array $validated = []): LengthAwarePaginator
    {
        $user = $this->getUser();

        return $user->orders()
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    private function checkUser(Order $order): void
    {
        if ($order->user_id !== $this->getUser()->id) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Order not found')
                    ->getLang('order_not_found')
            );
        }
    }

    public function show(Order $order): Order
    {
        $this->checkUser($order);

        $productVariantRelations = ProductService::instance()->getVariantRelationsForSite('', true);

        $order
            ->loadMissing([
                'items' => function ($query) use ($productVariantRelations) {
                    $query->with([
                        'productVariant' => function ($q) use ($productVariantRelations) {
                            $q->withTrashed();
                            $q->when(count($productVariantRelations['with']) > 0, fn($q) => $q->with($productVariantRelations['with']));
                        },
                        'companyProductVariant' => function ($q) {
                            $q->with(['city', 'city.translatable', 'media', 'company']);
                        },
                    ]);
                },
            ]);


        return $order;
    }

    public function details(Order $order): Order
    {
        $this->checkUser($order);

        $productVariantRelations = ProductService::instance()->getVariantRelationsForSite('', true);

        $order
            ->loadMissing([
                'items' => function ($query) use ($productVariantRelations) {
                    $query->with([
                        'productVariant' => function ($q) use ($productVariantRelations) {
                            $q->withTrashed();
                            $q->when(count($productVariantRelations['with']) > 0, fn($q) => $q->with($productVariantRelations['with']));
                        },
                        'companyProductVariant' => function ($q) {
                            $q->with(['city', 'city.translatable', 'media', 'company']);
                        },
                    ]);
                },
                'shippings.company',
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
                'statuses',
            ]);

        return $order;
    }

    public function buildCompaniesList(Order $order): array
    {
        $activeItemCompanyIds = [];
        if ($order->relationLoaded('items')) {
            foreach ($order->items as $item) {
                if ($item->status === OrderItemStatusEnum::CANCELED) {
                    continue;
                }
                $companyId = $item->companyProductVariant?->company_id;
                if ($companyId) {
                    $activeItemCompanyIds[$companyId] = true;
                }
            }
        }

        return $order->shippings
            ->map(fn(OrderShipping $shipping) => [
                'id'                => $shipping->company_id,
                'title'             => $shipping->company?->getLang('title'),
                'store_code'        => $shipping->company?->store_code,
                'wolt_status'       => $shipping->wolt_status,
                'has_active_items'  => isset($activeItemCompanyIds[$shipping->company_id]),
            ])
            ->values()
            ->all();
    }

    public function makeStatusesTree(Collection $statues): array
    {
        $statusesTree = [];

        $statuses = [
            OrderStatusEnum::PROCESSING,
            OrderStatusEnum::CONFIRMED,
            OrderStatusEnum::SHIPPED,
            OrderStatusEnum::DELIVERED,
        ];

        foreach ($statuses as $status) {
            /** @var OrderStatus $checkStatus */
            $checkStatus = $statues->where('status', $status)->first();
            $statusesTree[] = [
                'status' => $status,
                'status_label' => LangService::instance()
                    ->setDefault(Str::title($status->value))
                    ->getLang('order_status_' . $status->value),
                'is_checked' => (bool)$checkStatus,
                'date' => $checkStatus?->created_at?->format('d.m.Y, H:i') ?? null,
            ];
        }

        return $statusesTree;
    }

    public function pay(Order $order, OrderPaymentStatusEnum $paymentStatusEnum, ?string $transactionId): void
    {
        if ($order->status !== OrderStatusEnum::PAYMENT_PENDING || $order->payment_status !== OrderPaymentStatusEnum::PENDING) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Order cannot be paid')
                    ->getLang('order_cannot_be_paid')
            );
        }

        if ($transactionId !== null && $order->transaction_id !== $transactionId) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid transaction ID')
                    ->getLang('invalid_transaction_id')
            );
        }

        $order->update([
            'status' => $paymentStatusEnum === OrderPaymentStatusEnum::PAID ? OrderStatusEnum::PROCESSING : OrderStatusEnum::FAILED,
            'payment_status' => $paymentStatusEnum,
            'paid_at' => $paymentStatusEnum === OrderPaymentStatusEnum::PAID ? Carbon::now() : null,
        ]);

        if ($paymentStatusEnum === OrderPaymentStatusEnum::PAID) {
            // Clear the basket only after the payment is confirmed (redirect/callback methods).
            BasketService::instance()->clearBasketForOrder($order);
        } else {
            // Payment failed: release the stock reserved at checkout.
            $this->restoreStock($order);
        }
    }

    /**
     * Return the stock that was reserved at checkout. Called when an order's
     * payment ultimately fails, so the reserved units become available again.
     */
    public function restoreStock(Order $order): void
    {
        $items = $order->items()
            ->whereNotNull('company_product_variant_id')
            ->get(['id', 'company_product_variant_id', 'quantity']);

        foreach ($items as $item) {
            $this->restoreStockForItem($item);
        }
    }

    /**
     * Return a single order item's reserved stock to its seller listing.
     */
    public function restoreStockForItem(OrderItem $item): void
    {
        if (!$item->company_product_variant_id) {
            return;
        }

        CompanyProductVariant::query()
            ->whereKey($item->company_product_variant_id)
            ->increment('stock', $item->quantity);
    }

    public function getOrderByUuid(string $uuid): ?Order
    {
        return Order::query()->where('uuid', $uuid)->first();
    }

    public function checkPaymentStatusFromDatabase(Order $order, string $paymentMethod, string $token): string
    {
        $this->checkPaymentToken($order, $paymentMethod, $token);

        $this->checkUser($order);

        if ($order->payment_status === OrderPaymentStatusEnum::PAID) {
            return 'paid';
        }

        if ($order->payment_status === OrderPaymentStatusEnum::FAILED) {
            return 'failed';
        }

        if ($order->payment_status === OrderPaymentStatusEnum::REFUNDED) {
            return 'refunded';
        }

        return 'pending';
    }

    public function getPaymentToken(Order $order, string $paymentMethodName): string
    {
        return md5(sha1(md5($order->uuid . $paymentMethodName . $order->id . $order->getRawOriginal('amount_final'))));
    }

    public function checkPaymentToken(Order $order, string $paymentMethod, string $token): void
    {
        if ($this->getPaymentToken($order, $paymentMethod) !== $token) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Invalid token')
                    ->getLang('invalid_token')
            );
        }
    }

    public function getOrderStatusesList(): array
    {
        return OrderStatusEnum::getList();
    }
}
