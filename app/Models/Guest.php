<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $identifier
 */
class Guest extends Model
{
    use SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'device_type', // web, ios, android
        'identifier',
    ];

    protected array $cascadeDeletes = [
        'wishlists',
        'baskets',
    ];

    public function wishlists(): MorphMany
    {
        return $this->morphMany(Wishlist::class, 'userable');
    }

    public function wishlistProducts(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'userable', Wishlist::class)
            ->whereNull('wishlists.deleted_at')
            ->orderBy('wishlists.id');
    }

    public function wishlistProductVariants(): MorphToMany
    {
        return $this->morphToMany(ProductVariant::class, 'userable', Wishlist::class)
            ->whereNull('wishlists.deleted_at')
            ->orderBy('wishlists.id')
            ->withPivot(['company_product_variant_id']);
    }

    public function baskets(): MorphMany
    {
        return $this->morphMany(Basket::class, 'userable');
    }

    public function basketProducts(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'userable', Basket::class)
            ->whereNull('baskets.deleted_at')
            ->orderBy('baskets.id')
            ->withPivot(['quantity', 'sets', 'id']);
    }

    public function basketProductVariants(): MorphToMany
    {
        return $this->morphToMany(ProductVariant::class, 'userable', Basket::class)
            ->whereNull('baskets.deleted_at')
            ->orderBy('baskets.id')
            ->withPivot(['quantity', 'sets', 'id', 'company_product_variant_id', 'selected_brand_id', 'selected_brand_model_id']);
    }
}
