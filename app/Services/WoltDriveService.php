<?php

namespace App\Services;

use App\Enum\ProductSizeCategoryEnum;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderShipping;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\WoltException;

class WoltDriveService
{
    private static ?WoltDriveService $instance = null;

    private function __construct() {}

    public static function instance(): WoltDriveService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Create a Wolt delivery order for a group of order items.
     * Used by admin panel when triggering delivery for a specific seller.
     *
     * @param  Company         $company     Pickup (seller)
     * @param  Order           $order       The customer order (has dropoff address)
     * @param  Collection      $orderItems  Items belonging to this company + weight group
     * @param  int             $partNumber  1-based split index (for 20kg splits)
     * @return array  {wolt_order_reference_id, tracking_url, amount_cents}
     */
    public function createDeliveryOrder(
        Company $company,
        Order $order,
        Collection $orderItems,
        int $partNumber = 1
    ): array {
        $items       = $this->orderItemsToSpec($orderItems);
        $needsSedan  = $this->requiresSedan($items);
        $refId       = "ORDER-{$order->uuid}-COMPANY-{$company->id}-PART-{$partNumber}";
        $dropComment = $needsSedan ? config('wolt.sedan_note') : null;

        $response = $this->client()
            ->retry(3, 1000, fn($e) => $e instanceof ConnectionException || ($e instanceof RequestException && $e->response?->serverError()))
            ->post($this->url("/merchants/{$this->merchantId()}/delivery-order"), array_filter([
                'pickup'   => array_merge(
                    $this->buildPickupLocation($company),
                    ['contact_details' => $this->buildCompanyContact($company),
                     'comment'         => "Sifariş #{$order->id}"],
                ),
                'dropoff'  => array_filter([
                    'location'        => [
                        'formatted_address' => $order->address_line_1,
                        'coordinates'       => ['lat' => (float) $order->latitude, 'lon' => (float) $order->longitude],
                    ],
                    'contact_details' => $this->buildCustomerContact($order),
                    'comment'         => $dropComment,
                ]),
                'merchant_order_reference_id' => $refId,
                'order_number'                => (string) $order->id,
                'customer_support'            => array_filter([
                    'phone_number' => config('wolt.support_phone') ?: null,
                    'email'        => config('wolt.support_email') ?: null,
                ]),
                'contents' => $this->buildContents($items),
            ]));

        if ($response->failed()) {
            $this->logError('delivery-order', $response->status(), $response->body());
            throw new \RuntimeException("Wolt delivery order failed: " . $response->body());
        }

        $data = $response->json();

        return [
            'wolt_order_reference_id' => $data['wolt_order_reference_id'],
            'tracking_url'            => $data['tracking']['url'] ?? null,
            'amount_cents'            => $data['price']['amount'] ?? null,
        ];
    }

    /**
     * Cancel an active Wolt delivery.
     */
    public function cancelDelivery(string $woltOrderReferenceId, string $reason): bool
    {
        try {
            $response = $this->client()
                ->retry(3, 1000)
                ->patch($this->url("/order/{$woltOrderReferenceId}/status/cancel"), [
                    'reason' => $reason,
                ]);

            if ($response->failed()) {
                $this->logError('cancel', $response->status(), $response->body());

                return false;
            }

            return true;
        } catch (ConnectionException $e) {
            Log::error('Wolt cancel connection failed', ['wolt_id' => $woltOrderReferenceId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Verify a Wolt webhook JWT (HS256) and return its payload.
     * Returns false if signature is invalid or token is malformed.
     */
    public function verifyWebhookSignature(string $jwtToken): array|false
    {
        $parts = explode('.', $jwtToken);

        if (count($parts) !== 3) {
            return false;
        }

        [$header64, $payload64, $signature64] = $parts;

        $expectedSig = hash_hmac('sha256', "{$header64}.{$payload64}", config('wolt.client_secret'), true);
        $expectedSig64 = rtrim(strtr(base64_encode($expectedSig), '+/', '-_'), '=');

        if (!hash_equals($expectedSig64, $signature64)) {
            return false;
        }

        $payload = base64_decode(strtr($payload64, '-_', '+/'));
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : false;
    }

    /**
     * Split order items into groups where each group's total weight ≤ 20 kg.
     * Returns array of Collections (one per Wolt delivery order).
     *
     * @param  Collection  $orderItems  OrderItem[] with companyProductVariant loaded
     * @return Collection[]
     */
    public function splitItemsByWeight(Collection $orderItems): array
    {
        $maxGram = config('wolt.max_weight_gram');
        $groups  = [];
        $current = collect();
        $currentWeight = 0;

        foreach ($orderItems as $item) {
            $itemWeight = ($item->companyProductVariant?->weight_gram ?? 0) * $item->quantity;

            if ($current->isNotEmpty() && ($currentWeight + $itemWeight) > $maxGram) {
                $groups[]      = $current;
                $current       = collect();
                $currentWeight = 0;
            }

            $current->push($item);
            $currentWeight += $itemWeight;
        }

        if ($current->isNotEmpty()) {
            $groups[] = $current;
        }

        return $groups ?: [collect()];
    }

    /**
     * Total weight of items in grams (quantity-aware).
     *
     * @param  array  $items  [{weight_gram, quantity}]
     */
    public function totalWeightGram(array $items): int
    {
        return (int) array_sum(array_map(
            fn($item) => ($item['weight_gram'] ?? 0) * ($item['quantity'] ?? 1),
            $items
        ));
    }

    /**
     * Whether any item requires sedan (large size category).
     *
     * @param  array  $items  [{size_category}]
     */
    public function requiresSedan(array $items): bool
    {
        foreach ($items as $item) {
            $cat = $item['size_category'] ?? null;
            if ($cat instanceof ProductSizeCategoryEnum) {
                if ($cat === ProductSizeCategoryEnum::LARGE) return true;
            } elseif ($cat === 'large') {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert CPV-based item array to Wolt contents format.
     *
     * @param  array  $items  [{weight_gram, size_category, quantity, amount_cents}]
     */
    public function buildContents(array $items): array
    {
        $sizeMap = [
            'small'  => ['width_cm' => 15, 'height_cm' => 10, 'depth_cm' => 10],
            'medium' => ['width_cm' => 30, 'height_cm' => 20, 'depth_cm' => 20],
            'large'  => ['width_cm' => 60, 'height_cm' => 40, 'depth_cm' => 40],
        ];

        return array_values(array_map(function ($item) use ($sizeMap) {
            $cat  = $item['size_category'] instanceof ProductSizeCategoryEnum
                ? $item['size_category']->value
                : ($item['size_category'] ?? 'medium');

            $dims = $sizeMap[$cat] ?? $sizeMap['medium'];

            return array_merge([
                'count'       => $item['quantity'] ?? 1,
                'weight_gram' => $item['weight_gram'] ?? 0,
                'price'       => [
                    'amount'   => $item['amount_cents'] ?? 0,
                    'currency' => 'AZN',
                ],
            ], $dims);
        }, $items));
    }

    /**
     * Parallel fee calculation for multiple companies.
     * Cached companies are resolved instantly; uncached ones are fetched via Http::pool().
     *
     * @param  array  $specs  [company_id => ['company'=>Company, 'dropLat'=>float, 'dropLon'=>float, 'dropAddr'=>string, 'items'=>array]]
     * @return array          [company_id => {amount_cents, eta_minutes, is_sedan, is_estimate}]
     */
    public function getDeliveryFeesBatch(array $specs): array
    {
        $results  = [];
        $toFetch  = [];

        foreach ($specs as $companyId => $spec) {
            $needsSedan = $this->requiresSedan($spec['items']);
            $cacheKey   = $this->feeCacheKey($companyId, $spec['dropLat'], $spec['dropLon'], $needsSedan);
            $cached     = Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$companyId] = $cached;
            } else {
                $toFetch[$companyId] = array_merge($spec, [
                    'is_sedan'  => $needsSedan,
                    'cache_key' => $cacheKey,
                ]);
            }
        }

        if (empty($toFetch)) {
            return $results;
        }

        $responses = Http::pool(function ($pool) use ($toFetch) {
            $requests = [];
            foreach ($toFetch as $companyId => $spec) {
                $requests[] = $pool->as((string) $companyId)
                    ->withToken(config('wolt.api_key'))
                    ->timeout(config('wolt.timeout'))
                    ->acceptJson()
                    ->asJson()
                    ->post($this->url("/merchants/{$this->merchantId()}/delivery-fee"), [
                        'pickup'   => $this->buildPickupLocation($spec['company']),
                        'dropoff'  => [
                            'location' => [
                                'formatted_address' => $spec['dropAddr'],
                                'coordinates'       => ['lat' => $spec['dropLat'], 'lon' => $spec['dropLon']],
                            ],
                        ],
                        'contents' => $this->buildContents($spec['items']),
                    ]);
            }

            return $requests;
        });

        foreach ($toFetch as $companyId => $spec) {
            $response = $responses[(string) $companyId] ?? null;

            if ($response instanceof \Throwable || !$response || $response->failed()) {
                $status   = $response instanceof \Throwable ? 0 : ($response?->status() ?? 0);
                $body     = $response instanceof \Throwable ? $response->getMessage() : ($response?->body() ?? '');
                $this->logError('delivery-fee-batch', $status, $body);
                $woltData = json_decode($body, true);
                $reason   = $woltData['reason'] ?? $woltData['details'] ?? $body;
                $prefix = LangService::instance()->setDefault('Delivery error')->getLang('delivery_error');
                throw new WoltException("{$prefix}: {$reason}");
            }

            $data   = $response->json();
            $result = [
                'amount_cents' => $data['fee']['amount'],
                'eta_minutes'  => $data['time_estimate_minutes'] ?? null,
                'is_sedan'     => $spec['is_sedan'],
                'is_estimate'  => false,
            ];

            Cache::put($spec['cache_key'], $result, config('wolt.fee_cache_ttl'));
            $results[$companyId] = $result;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function client()
    {
        return Http::withToken(config('wolt.api_key'))
            ->timeout(config('wolt.timeout'))
            ->acceptJson()
            ->asJson();
    }

    private function url(string $path): string
    {
        return rtrim(config('wolt.base_url'), '/') . $path;
    }

    private function merchantId(): string
    {
        return config('wolt.merchant_id');
    }

    private function feeCacheKey(int $companyId, float $lat, float $lon, bool $isSedan): string
    {
        $latKey = round($lat, 3);
        $lonKey = round($lon, 3);
        $sedanKey = $isSedan ? '1' : '0';

        return "wolt_fee_{$companyId}_{$latKey}_{$lonKey}_{$sedanKey}";
    }

    private function buildPickupLocation(Company $company): array
    {
        return [
            'location' => [
                'formatted_address' => $company->getLang('address') ?? '',
                'coordinates'       => [
                    'lat' => (float) $company->latitude,
                    'lon' => (float) $company->longitude,
                ],
            ],
        ];
    }

    private function buildCompanyContact(Company $company): array
    {
        return array_filter([
            'name'         => $company->getLang('title') ?? '',
            'phone_number' => $company->phone ?? null,
        ]);
    }

    private function buildCustomerContact(Order $order): array
    {
        return array_filter([
            'name'         => $order->user?->full_name ?? '',
            'phone_number' => $order->phone ?? null,
        ]);
    }

    /**
     * Convert a Collection of OrderItem models to the item spec array.
     * Requires companyProductVariant to be loaded on each item.
     */
    private function orderItemsToSpec(Collection $orderItems): array
    {
        return $orderItems->map(function (OrderItem $item) {
            $cpv = $item->companyProductVariant;

            return [
                'weight_gram'   => $cpv?->weight_gram ?? 0,
                'size_category' => $cpv?->size_category ?? ProductSizeCategoryEnum::MEDIUM,
                'quantity'      => $item->quantity,
                'amount_cents'  => $item->getRawOriginal('amount_final') ?? 0,
            ];
        })->all();
    }

    private function logError(string $operation, int $status, string $body): void
    {
        Log::error("Wolt {$operation} failed", ['status' => $status, 'body' => $body]);
    }
}
