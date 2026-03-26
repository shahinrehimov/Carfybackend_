<?php

namespace App\Services;

use App\DTOs\Payment\PaymentResult;
use App\Enum\OrderPaymentStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\CompanyProductVariant;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Payment\PaymentService;
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

    public function checkout(array $validated): PaymentResult
    {
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

        /** @var Order $order */
        $order = DB::transaction(function () use ($user, $validated, $productVariants, $address) {
            $items = [];

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
                ];
            }

            $basketData = BasketService::instance()->calculateBasketData($productVariants);

            /** @var Order $order */
            $order = $user->orders()->create([
                'status' => OrderStatusEnum::PENDING,
                'payment_status' => OrderPaymentStatusEnum::PENDING,
                'payment_method' => $validated['payment_method'],
                'amount' => $basketData['total_price_in_basket'],
                'amount_final' => $basketData['total_price_final_in_basket'],
                'total_discount_in_basket' => $basketData['total_discount_in_basket'],
                'shipping' => $basketData['shipping'],
                'total_quantity' => $basketData['total_basket_quantity'],
                'address_type_id' => $address->address_type_id,
                'city_id' => $address->city_id,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'phone' => $address->phone,
            ]);

            $order->items()->createMany($items);

            BasketService::instance()->clearBasket($user);

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
                }
            }
        } else if (!$result->isPending) {
            if ($order->status === OrderStatusEnum::PAYMENT_PENDING && $order->payment_status === OrderPaymentStatusEnum::PENDING) {
                $this->pay($order, OrderPaymentStatusEnum::FAILED, $result->transactionId);
            }
        }

        return $result;
    }

    public function refund(Order $order, ?int $amount = null, string $comment = ''): PaymentResult
    {
        if ($order->payment_status !== OrderPaymentStatusEnum::PAID || in_array($order->status, [OrderStatusEnum::RETURNED, OrderPaymentStatusEnum::PARTIALLY_REFUNDED, OrderStatusEnum::CANCELED, OrderStatusEnum::DELIVERED])) {
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
        }

        return $result;
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
                'addressType',
                'addressType.translatable',
                'city',
                'city.translatable',
                'statuses',
            ]);

        return $order;
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

    public function pay(Order $order, OrderPaymentStatusEnum $paymentStatusEnum, string $transactionId): void
    {
        if ($order->status !== OrderStatusEnum::PAYMENT_PENDING || $order->payment_status !== OrderPaymentStatusEnum::PENDING) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Order cannot be paid')
                    ->getLang('order_cannot_be_paid')
            );
        }

        if ($order->transaction_id !== $transactionId) {
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
}
