<?php

namespace App\Services;

use App\Models\Basket;
use App\Models\CompanyProductVariant;
use App\Models\Guest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\ProductVariantRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class BasketService
{
    private static ?BasketService $instance = null;

    private function __construct()
    {

    }

    public static function instance(): BasketService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function add(CompanyProductVariant $cpv, int $quantity, array $sets = []): bool
    {
        $this->checkStock($cpv, $quantity);

        $cpv->loadMissing(['productVariant']);
        $productVariant = $cpv->productVariant;

        $productVariant = ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        ProductService::instance()->setProductVariant($productVariant)->loadVariantRelationsForSite();

        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        $productVariant->loadMissing([
            'product' => function ($builder) use ($relations) {
                $builder->with([
                    'sets' => function ($query) use ($relations) {
                        $query->active();
                        $query->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']));
                        $query->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']));
                        $query->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']));
                    }
                ]);
            }
        ]);

        [$productVariant, $sets] = $this->checkSets($productVariant, $quantity, $sets);

        $basket = DB::transaction(function () use ($cpv, $productVariant, $quantity, $sets) {
            return UserService::instance()->getUserOrGuest()
                ->baskets()
                ->updateOrCreate(
                    [
                        'company_product_variant_id' => $cpv->id,
                        'product_variant_id' => $productVariant->id,
                        'product_id' => $productVariant->product_id,
                    ],
                    [
                        'quantity' => $quantity,
                        'is_set' => $productVariant->isSet(),
                        'sets' => $productVariant->isSet() ? $sets : null,
                    ]
                );
        });

        $this->calculateBasketQuantity($productVariant, $quantity, $sets);

        if (!$basket->wasRecentlyCreated) {
            return false;
        }

        return true;
    }

    public function increment(CompanyProductVariant $cpv): int
    {
        $cpv->loadMissing(['productVariant']);
        $productVariant = $cpv->productVariant;
        ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        $basket = UserService::instance()->getUserOrGuest()
            ->baskets()
            ->where('company_product_variant_id', $cpv->id)
            ->first();

        if (!$basket) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product is not in the basket!')
                    ->getLang('basket_product_not_in_basket')
            );
        }

        $this->checkStock($cpv, $basket->quantity + 1);
        $this->checkSets($productVariant, $basket->quantity + 1, $basket->sets ?? []);

        DB::transaction(function () use ($basket) {
            $basket->increment('quantity');
        });

        return $basket->quantity;
    }

    public function decrement(CompanyProductVariant $cpv): int
    {
        $cpv->loadMissing(['productVariant']);
        $productVariant = $cpv->productVariant;
        ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        $basket = UserService::instance()->getUserOrGuest()
            ->baskets()
            ->where('company_product_variant_id', $cpv->id)
            ->first();

        if (!$basket) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product is not in the basket!')
                    ->getLang('basket_product_not_in_basket')
            );
        }

        if ($basket->quantity > 1) {
            $this->checkSets($productVariant, $basket->quantity - 1, $basket->sets ?? []);
        }

        return DB::transaction(function () use ($basket) {
            if ($basket->quantity <= 1) {
                $basket->delete();

                return 0;
            }

            $basket->decrement('quantity');

            return $basket->quantity;
        });
    }

    public function incrementSetItem(CompanyProductVariant $cpv, ProductVariant $setItem): int
    {
        [$basket, $sets, $selectedIndex] = $this->checkSetForQuantityChange($cpv, $setItem);

        if ($selectedIndex === null) {
            $newQuantity = 1;

            $sets[] = [
                'id' => $setItem->id,
                'quantity' => $newQuantity,
            ];
        } else {
            $newQuantity = $sets[$selectedIndex]['quantity'] + 1;

            $sets[$selectedIndex]['quantity'] = $newQuantity;
        }

        $basket->update([
            'sets' => $sets,
        ]);

        return $newQuantity;
    }

    public function decrementSetItem(CompanyProductVariant $cpv, ProductVariant $setItem): int
    {
        [$basket, $sets, $selectedIndex] = $this->checkSetForQuantityChange($cpv, $setItem);

        if ($selectedIndex === null) {
            $newQuantity = 0;

            $sets[] = [
                'id' => $setItem->id,
                'quantity' => $newQuantity,
            ];
        } else {
            $newQuantity = $sets[$selectedIndex]['quantity'] - 1;

            $sets[$selectedIndex]['quantity'] = $newQuantity;
        }

        if ($newQuantity < 0) {
            $setItem->loadMissing([
                'product',
                'product.translatable',
            ]);

            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Cannot have negative quantity of product set item in the basket: @product')
                    ->getLang('basket_negative_quantity_for_product_set', [
                        '@product' => $setItem->product->getLang('title'),
                    ])
            );
        }

        $basket->update([
            'sets' => $sets,
        ]);

        return $newQuantity;
    }

    public function removeSetItem(CompanyProductVariant $cpv, ProductVariant $setItem): bool
    {
        [$basket, $sets, $selectedIndex] = $this->checkSetForQuantityChange($cpv, $setItem);

        if ($selectedIndex === null) {
            return false;
        }

        $sets = array_filter($sets, fn($set) => $set['id'] !== $setItem->id);

        $basket->update([
            'sets' => array_values($sets),
        ]);

        return true;
    }

    private function checkSetForQuantityChange(CompanyProductVariant $cpv, ProductVariant $setItem): array
    {
        $cpv->loadMissing(['productVariant']);
        $productVariant = $cpv->productVariant;

        /** @var Basket $basket */
        $basket = UserService::instance()->getUserOrGuest()
            ->baskets()
            ->where('company_product_variant_id', $cpv->id)
            ->first();

        if (!$basket) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Product is not in the basket!')
                    ->getLang('basket_product_not_in_basket')
            );
        }

        if (!$basket->isSet()) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Basket is not a set!')
                    ->getLang('basket_not_a_set')
            );
        }

        ProductService::instance()->checkSetItem($productVariant, $setItem);

        $sets = $basket->sets ?? [];

        if (count($sets) === 0) {
            $productVariant->loadMissing([
                'sets' => function ($builder) {
                    $builder->active();
                    $builder->with([
                        'product',
                        'product.translatable',
                    ]);
                }
            ]);

            foreach ($productVariant->sets as $set) {
                $sets[] = [
                    'id' => $set->id,
                    'quantity' => $set->pivot->quantity,
                ];
            }
        }

        $selectedIndex = null;
        foreach ($sets as $index => $set) {
            if ($set['id'] === $setItem->id) {
                $selectedIndex = $index;
                break;
            }
        }

        return [$basket, $sets, $selectedIndex];
    }

    public function ids(): array
    {
        $cpvIds = UserService::instance()->getUserOrGuest()
            ->baskets()
            ->pluck('company_product_variant_id')
            ->filter()
            ->unique()
            ->values();

        if ($cpvIds->isEmpty()) {
            return [];
        }

        return CompanyProductVariant::query()
            ->whereIn('id', $cpvIds)
            ->pluck('uuid')
            ->toArray();
    }

    public function remove(CompanyProductVariant $cpv): bool
    {
        return DB::transaction(function () use ($cpv) {
            $basket = UserService::instance()->getUserOrGuest()
                ->baskets()
                ->where('company_product_variant_id', $cpv->id)
                ->first();

            if (!$basket) {
                return false;
            }

            $basket->delete();

            return true;
        });
    }

    public function load(array $validated): array
    {
        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        $products = UserService::instance()->getUserOrGuest()
            ->basketProductVariants()
            ->active()
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->with([
                'sets' => function ($query) use ($relations) {
                    $query->active();
                    $query->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']));
                    $query->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']));
                    $query->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']));
                }
            ])
            ->get();

        $this->batchLoadSellerData($products);

        foreach ($products as $product) {
            $this->calculateBasketQuantity($product);
        }

        $this->attachListingFlags($products);

        return [$products, $this->calculateBasketData($products)];
    }

    /**
     * Fill in the in_wishlist / in_basket / ratings data the canonical product
     * list structure expects, both on the seller listing and on the variant.
     */
    private function attachListingFlags(Collection $productVariants): void
    {
        $cpvs = $productVariants
            ->map(fn(ProductVariant $pv) => $pv->seller_listing ?? null)
            ->filter()
            ->values();

        $repository = new ProductVariantRepository();
        $repository->attachWishlistAndBasketToCpvsCollection($cpvs);
        $repository->attachRatingsToCpvsCollection($cpvs);

        foreach ($productVariants as $productVariant) {
            $cpv = $productVariant->seller_listing ?? null;

            $productVariant->in_wishlist = $cpv?->in_wishlist ?? false;
            $productVariant->in_basket = $cpv?->in_basket ?? true;
        }
    }

    public function calculateBasketData(?Collection $productVariants = null): array
    {
        if ($productVariants === null) {
            $productVariants = $this->loadProducts();
        }

        $totalPriceInBasket = $productVariants->sum('price_in_basket');
        $totalPriceFinalInBasket = $productVariants->sum('price_final_in_basket');

        return [
            'total_price_in_basket' => round($totalPriceInBasket, 2),
            'total_price_final_in_basket' => round($totalPriceFinalInBasket, 2),
            'total_discount_in_basket' => round($totalPriceInBasket - $totalPriceFinalInBasket, 2),
            'shipping' => 0,
            'total_basket_quantity' => $productVariants->sum('basket_quantity'),
        ];
    }

    public function loadProducts(?User $user = null): Collection
    {
        if ($user === null) {
            $user = UserService::instance()->getUserOrGuest();
        }

        // Site relations are needed because the checkout preview renders these
        // listings through the canonical product list resource.
        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        $productVariants = $user
            ->basketProductVariants()
            ->active()
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->with([
                'sets' => function ($query) {
                    $query->active();
                }
            ])
            ->get();

        $this->batchLoadSellerData($productVariants);

        foreach ($productVariants as $productVariant) {
            $this->calculateBasketQuantity($productVariant);
        }

        return $productVariants;
    }

    private function batchLoadSellerData(Collection $productVariants): void
    {
        $cpvIds = $productVariants
            ->map(fn($pv) => $pv->pivot->company_product_variant_id ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($cpvIds->isEmpty()) {
            return;
        }

        $cpvs = CompanyProductVariant::query()
            ->with([
                'city', 'city.translatable', 'media', 'company',
                'productVariant' => fn($q) => $q->with(['media', 'product.media']),
            ])
            ->whereIn('id', $cpvIds)
            ->get()
            ->keyBy('id');

        foreach ($productVariants as $productVariant) {
            $cpvId = $productVariant->pivot->company_product_variant_id ?? null;

            if ($cpvId && $cpvs->has($cpvId)) {
                $cpv = $cpvs->get($cpvId);

                // Swap in the variant that was already loaded with every relation
                // the canonical list resource needs, so nothing gets lazy loaded.
                $cpv->setRelation('productVariant', $productVariant);

                $productVariant->seller_listing = $cpv;
                // Override price fields with seller-specific raw values (bypass casts)
                $productVariant->setRawAttributes(array_merge(
                    $productVariant->getAttributes(),
                    [
                        'stock' => $cpv->getRawOriginal('stock'),
                        'price' => $cpv->getRawOriginal('price'),
                        'discount_type' => $cpv->getRawOriginal('discount_type'),
                        'discount' => $cpv->getRawOriginal('discount'),
                        'discount_start_at' => $cpv->getRawOriginal('discount_start_at'),
                        'discount_end_at' => $cpv->getRawOriginal('discount_end_at'),
                    ]
                ), true);
            }
        }
    }

    private function calculateBasketQuantity(ProductVariant $productVariant, ?int $quantity = null, ?array $sets = null): void
    {
        $productVariant->basket_quantity = $quantity === null ? ($productVariant->pivot->quantity ?? 0) : $quantity;

        // Keep the seller listing in sync so price_in_basket resolves on it too.
        if ($productVariant->seller_listing ?? null) {
            $productVariant->seller_listing->basket_quantity = $productVariant->basket_quantity;
            $productVariant->seller_listing->in_basket = true;
        }

        if ($productVariant->isSet()) {
            if ($sets === null) {
                $rawSets = $productVariant->pivot?->sets;
                $pivotSets = collect(is_string($rawSets) ? json_decode($rawSets, true) : ($rawSets ?? []));
            } else {
                $pivotSets = collect($sets);
            }

            $productVariant->sets = $productVariant->sets->map(function ($set) use ($pivotSets) {
                $set->basket_quantity = $pivotSets->where('id', $set->id)->first()['quantity'] ?? 0;

                return $set;
            });
        }
    }

    public function moveToUser(User|Guest $fromUser, User|Guest $toUser): void
    {
        if ($fromUser->baskets()->exists()) {
            $toUser->baskets()
                ->update([
                    'deletable_id' => $toUser->id,
                    'deletable_type' => $toUser->getMorphClass(),
                    'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                    'deleted_at' => Carbon::now(),
                ]);

            $fromUser->baskets()
                ->update([
                    'userable_id' => $toUser->id,
                    'userable_type' => $toUser->getMorphClass(),
                ]);
        }
    }

    private function checkSets(ProductVariant $productVariant, int $quantity, array $sets = []): array
    {
        if ($productVariant->isSet()) {
            $productVariant->loadMissing([
                'sets' => function ($builder) {
                    $builder->active();
                    $builder->with([
                        'product',
                        'product.translatable',
                    ]);
                }
            ]);

            $setsArrayCount = count($sets);

            if ($setsArrayCount > $productVariant->sets->count()) {
                LoggerService::instance()->log('Invalid product sets provided for productVariant ID: ' . $productVariant->id . ', provided sets count: ' . $setsArrayCount . ', available sets count: ' . $productVariant->sets->count(), [], true);

                throw new BadRequestHttpException(
                    LangService::instance()
                        ->setDefault('Invalid product sets provided!')
                        ->getLang('basket_invalid_product_sets_provided')
                );
            }

            if ($setsArrayCount === 0) {
                foreach ($productVariant->sets as $set) {
                    $sets[] = [
                        'id' => $set->id,
                        'quantity' => $set->pivot->quantity,
                    ];
                }
            }

            foreach ($sets as $index => $set) {
                $sets[$index]['id'] = (int)$set['id'];
                $sets[$index]['quantity'] = (int)$set['quantity'];

                /** @var ProductVariant $setItem */
                $setItem = $productVariant->sets->where('id', $set['id'])->first();

                if (!$setItem) {
                    LoggerService::instance()->log('Invalid product set ID: ' . $set['id'] . ' provided for productVariant ID: ' . $productVariant->id, [], true);

                    throw new BadRequestHttpException(
                        LangService::instance()
                            ->setDefault('Invalid product sets provided!')
                            ->getLang('basket_invalid_product_sets_provided')
                    );
                }
            }
        }

        return [$productVariant, $sets];
    }

    private function checkStock(CompanyProductVariant $cpv, int $quantity): void
    {
        if ($cpv->getRawOriginal('stock') < $quantity) {
            $cpv->loadMissing(['productVariant', 'productVariant.product', 'productVariant.product.translatable']);

            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Insufficient stock for this product. Available: @stock')
                    ->getLang('basket_insufficient_stock', [
                        '@stock' => $cpv->stock,
                    ])
            );
        }
    }

    public function clearBasket(?User $user = null): void
    {
        if ($user === null) {
            $user = UserService::instance()->getUserOrGuest();
        }

        $user->baskets()
            ->update([
                'deletable_id' => $user->id,
                'deletable_type' => $user->getMorphClass(),
                'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                'deleted_at' => Carbon::now(),
            ]);
    }

    /**
     * Clear only the basket rows that belong to a given order, leaving any
     * items the user added after checkout untouched. Called once the order's
     * payment is confirmed.
     */
    public function clearBasketForOrder(Order $order): void
    {
        $basketIds = $order->items()
            ->pluck('basket_id')
            ->filter()
            ->values();

        if ($basketIds->isEmpty()) {
            return;
        }

        Basket::query()
            ->whereIn('id', $basketIds)
            ->update([
                'deletable_id' => $order->user_id,
                'deletable_type' => (new User())->getMorphClass(),
                'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                'deleted_at' => Carbon::now(),
            ]);
    }
}
