<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\ProductVariant;
use App\Models\User;
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

    public function add(ProductVariant $productVariant): bool
    {
        $productVariant = ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        $wishlist = DB::transaction(function () use ($productVariant) {
            return UserService::instance()->getUserOrGuest()
                ->wishlists()
                ->firstOrCreate([
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

    public function remove(ProductVariant $productVariant): bool
    {
        $productVariant = ProductService::instance()->setProductVariant($productVariant)->getActiveProductVariant();

        return DB::transaction(function () use ($productVariant) {
            $wishlist = UserService::instance()->getUserOrGuest()
                ->wishlists()
                ->where('product_variant_id', $productVariant->id)
                ->first();

            if (!$wishlist) {
                return false;
            }

            $wishlist->delete();

            return true;
        });
    }

    public function load(array $validated): LengthAwarePaginator
    {
        $relations = ProductService::instance()->getVariantRelationsForSite('', true);

        return UserService::instance()->getUserOrGuest()
            ->wishlistProductVariants()
            ->active()
            ->when(count($relations['with']) > 0, fn($q) => $q->with($relations['with']))
            ->when(count($relations['exists']) > 0, fn($q) => $q->withExists($relations['exists']))
            ->when(count($relations['counts']) > 0, fn($q) => $q->withCount($relations['counts']))
            ->paginate($validated['limit'] ?? 10);
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
