<?php

namespace App\Services;

use App\Models\CompanyProductVariant;
use App\Models\Guest;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\ProductVariantRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    private static ?WishlistService $instance = null;

    private function __construct()
    {

    }

    public static function instance(): WishlistService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function add(CompanyProductVariant $cpv): bool
    {
        $cpv->loadMissing(['productVariant']);
        $productVariant = $cpv->productVariant;

        $productVariant = ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        $wishlist = DB::transaction(function () use ($cpv, $productVariant) {
            return UserService::instance()->getUserOrGuest()
                ->wishlists()
                ->firstOrCreate([
                    'company_product_variant_id' => $cpv->id,
                    'product_variant_id' => $productVariant->id,
                    'product_id' => $productVariant->product_id,
                ]);
        });

        if (!$wishlist->wasRecentlyCreated) {
            return false;
        }

        ProductService::instance()->setProductVariant($productVariant)->loadVariantRelationsForSite();

        return true;
    }

    public function remove(CompanyProductVariant $cpv): bool
    {
        return DB::transaction(function () use ($cpv) {
            $wishlist = UserService::instance()->getUserOrGuest()
                ->wishlists()
                ->where('company_product_variant_id', $cpv->id)
                ->first();

            if (!$wishlist) {
                return false;
            }

            $wishlist->delete();

            return true;
        });
    }

    public function ids(): array
    {
        $cpvIds = UserService::instance()->getUserOrGuest()
            ->wishlists()
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

    public function load(array $validated): LengthAwarePaginator
    {
        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        $products = UserService::instance()->getUserOrGuest()
            ->wishlistProductVariants()
            ->active()
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->paginate($validated['limit'] ?? 10);

        $this->batchLoadSellerData($products);

        return $products;
    }

    private function batchLoadSellerData(LengthAwarePaginator $productVariants): void
    {
        $items = collect($productVariants->items());

        $cpvIds = $items
            ->map(fn($pv) => $pv->pivot->company_product_variant_id ?? null)
            ->filter()
            ->unique()
            ->values();

        $cpvs = $cpvIds->isNotEmpty()
            ? CompanyProductVariant::query()
                ->with(['city', 'city.translatable', 'media', 'company', 'company.workingHours'])
                ->whereIn('id', $cpvIds)
                ->get()
                ->keyBy('id')
            : collect();

        $attachedCpvs = collect();

        foreach ($items as $productVariant) {
            $productVariant->in_wishlist = true;

            $cpvId = $productVariant->pivot->company_product_variant_id ?? null;

            if ($cpvId && $cpvs->has($cpvId)) {
                $cpv = $cpvs->get($cpvId);

                // The variant is already fully loaded here (media, product,
                // categories, options), so hand it to the listing to keep the
                // canonical list resource from lazy loading anything.
                $cpv->setRelation('productVariant', $productVariant);
                $attachedCpvs->push($cpv);

                $productVariant->seller_listing = $cpv;
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

        $repository = new ProductVariantRepository();
        $repository->attachWishlistAndBasketToCpvsCollection($attachedCpvs);
        $repository->attachRatingsToCpvsCollection($attachedCpvs);

        // Mirror the listing flags onto the variant for consumers reading them there.
        foreach ($items as $productVariant) {
            $cpv = $productVariant->seller_listing ?? null;

            $productVariant->in_wishlist = $cpv?->in_wishlist ?? true;
            $productVariant->in_basket = $cpv?->in_basket ?? false;
            $productVariant->basket_quantity = $cpv?->basket_quantity ?? 0;
        }
    }

    public function moveToUser(User|Guest $fromUser, User|Guest $toUser): void
    {
        if ($fromUser->wishlists()->exists()) {
            $toUser->wishlists()
                ->update([
                    'deletable_id' => $toUser->id,
                    'deletable_type' => $toUser->getMorphClass(),
                    'is_deleted' => DB::raw("CONCAT('deleted_', id)"),
                    'deleted_at' => Carbon::now(),
                ]);

            $fromUser->wishlists()
                ->update([
                    'userable_id' => $toUser->id,
                    'userable_type' => $toUser->getMorphClass(),
                ]);
        }
    }
}
