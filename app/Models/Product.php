<?php

namespace App\Models;

use App\Casts\Products\HoverImageCast;
use App\Casts\Products\ImagesCast;
use App\Casts\Products\MainImageCast;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $discount
 * @property mixed $price
 * @property mixed $discount_type
 * @property mixed $set_id
 * @property mixed $sets
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @property mixed $main_image
 * @property bool|mixed $is_active
 * @property mixed $pivot
 * @property mixed $categoriesRel
 * @property mixed $main_category_id
 * @property mixed $mainCategory
 * @property mixed $related_products
 * @property mixed $stock
 * @property int|mixed $basket_quantity
 * @property mixed $price_in_basket
 * @property mixed $price_final_in_basket
 * @property array|mixed $ratings
 * @property mixed $variants
 * @property mixed $company_id
 */

class Product extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, Translatable, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'company_id', // nullable
        'uuid',
        'slug',
        'is_set',
        'main_category_id',
        'brand_id',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
        'description_short',
        'description',
        'delivery',
        'note',
    ];

    protected $casts = [
        'is_set' => 'boolean',
        'is_active' => 'boolean',
        'main_image' => MainImageCast::class,
        'hover_image' => HoverImageCast::class,
        'images' => ImagesCast::class,
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'media',
        'categoriesRel',
        'groupsRel',
        'wishlists',
        'baskets',
        'reviews',
        'variants',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('products_main')->singleFile();
        $this->addMediaCollection('products_hover')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function scopeIsSet(Builder $builder): void
    {
        $builder->where('is_set', true);
    }

    public function scopeIsNotSet(Builder $builder): void
    {
        $builder->where('is_set', false);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function isSet()
    {
        return $this->is_set ?? false;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mainCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'main_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, ProductCategory::class, 'product_id', 'category_id')
            ->whereNull('product_categories.deleted_at');
    }

    public function categoriesRel(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, GroupProduct::class)
            ->whereNull('group_products.deleted_at');
    }

    public function groupsRel(): HasMany
    {
        return $this->hasMany(GroupProduct::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function baskets(): HasMany
    {
        return $this->hasMany(Basket::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
