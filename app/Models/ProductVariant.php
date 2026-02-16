<?php

namespace App\Models;

use App\Casts\ProductVariants\DiscountCast;
use App\Casts\ProductVariants\HoverImageCast;
use App\Casts\ProductVariants\ImagesCast;
use App\Casts\ProductVariants\MainImageCast;
use App\Casts\ProductVariants\PriceCast;
use App\Casts\ProductVariants\PriceFinalCast;
use App\Casts\ProductVariants\PriceFinalInBasketCast;
use App\Casts\ProductVariants\PriceInBasketCast;
use App\Casts\ProductVariants\TitleCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use Carbon\Carbon;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @property mixed $main_image
 * @property bool|mixed $is_active
 * @property bool|mixed $is_default
 * @property mixed $stock
 * @property int|mixed $basket_quantity
 * @property mixed $price_in_basket
 * @property mixed $price_final_in_basket
 * @property mixed $sets
 * @property mixed $pivot
 * @property mixed $product
 * @property mixed $product_id
 * @property array|mixed $ratings
 * @property Collection|mixed $related_products
 * @property mixed $options
 * @property mixed $company_id
 * @property mixed $approval_status
 * @property mixed|null $approval_status_message
 */

class ProductVariant extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'company_id', // nullable
        'brand_model_id', // nullable
        'uuid',
        'is_set',
        'is_default',
        'slug', // nullable, unique
        'product_id',
        'stock',
        'price', // in cents, null for sets
        'discount_type', // DiscountTypeEnum
        'discount',
        'discount_start_at',
        'discount_end_at',
        'is_active',
        'approval_status', // ProductVariantApprovalStatusEnum
        'approval_status_message', // nullable, max 3000
        'city_id',
        'brand_model_id',
    ];

    protected $casts = [
        'is_set' => 'boolean',
        'title' => TitleCast::class,
        'price' => PriceCast::class,
        'price_final' => PriceFinalCast::class,
        'price_in_basket' => PriceInBasketCast::class,
        'price_final_in_basket' => PriceFinalInBasketCast::class,
        'discount' => DiscountCast::class,
        'discount_start_at' => 'datetime',
        'discount_end_at' => 'datetime',
        'is_active' => 'boolean',
        'main_image' => MainImageCast::class,
        'hover_image' => HoverImageCast::class,
        'images' => ImagesCast::class,
    ];

    protected array $cascadeDeletes = [
        'media',
        'setsRel',
        'options',
        'groupsRel',
        'wishlists',
        'baskets',
        'reviews',
        'approvalStatuses',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_variants_main')->singleFile();
        $this->addMediaCollection('product_variants_hover')->singleFile();
    }

    public static function getPriceCalculationQuery(): string
    {
        $now = Carbon::now()->toDateTimeString();

        return "
                ROUND(
                    CASE
                        WHEN product_variants.is_set = false THEN
                            CASE
                                WHEN product_variants.discount IS NULL
                                    OR product_variants.discount = 0
                                    OR product_variants.discount_type IS NULL
                                    OR (product_variants.discount_start_at IS NOT NULL AND product_variants.discount_start_at > '{$now}')
                                    OR (product_variants.discount_end_at IS NOT NULL AND product_variants.discount_end_at < '{$now}')
                                THEN product_variants.price / 100.0
                                ELSE
                                    CASE
                                        WHEN product_variants.discount_type = 'by_percentage'
                                        THEN (product_variants.price - (product_variants.price * product_variants.discount / 100.0)) / 100.0
                                        WHEN product_variants.discount_type = 'by_amount'
                                        THEN (product_variants.price - product_variants.discount) / 100.0
                                        ELSE product_variants.price / 100.0
                                    END
                            END
                        ELSE
                            COALESCE((
                                SELECT SUM(
                                    CASE
                                        WHEN p.discount IS NULL
                                            OR p.discount = 0
                                            OR p.discount_type IS NULL
                                            OR (p.discount_start_at IS NOT NULL AND p.discount_start_at > '{$now}')
                                            OR (p.discount_end_at IS NOT NULL AND p.discount_end_at < '{$now}')
                                        THEN (p.price * ps.quantity) / 100.0
                                        ELSE
                                            CASE
                                                WHEN p.discount_type = 'by_percentage'
                                                THEN ((p.price * ps.quantity) - ((p.price * ps.quantity) * p.discount / 100.0)) / 100.0
                                                WHEN p.discount_type = 'by_amount'
                                                THEN ((p.price * ps.quantity) - p.discount) / 100.0
                                                ELSE (p.price * ps.quantity) / 100.0
                                            END
                                    END
                                )
                                FROM product_sets ps
                                JOIN product_variants p ON ps.product_id = p.id
                                WHERE ps.set_id = product_variants.id
                                AND p.deleted_at IS NULL
                            ), 0)
                    END
                , 2)
            ";
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function scopeDefault(Builder $builder): void
    {
        $builder->where('is_default', true);
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

    public function isDefault(): bool
    {
        return $this->is_default ?? true;
    }

    public function isSet()
    {
        return $this->is_set ?? false;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sets(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, ProductVariantSet::class, 'set_id', 'product_variant_id')
            ->withPivot(['quantity']);
    }

    public function setsRel(): HasMany
    {
        return $this->hasMany(ProductVariantSet::class, 'set_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductVariantProductOption::class, 'product_variant_id');
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

    public function approvalStatuses(): HasMany
    {
        return $this->hasMany(ProductVariantApprovalStatus::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function brandModel(): BelongsTo
    {
        return $this->belongsTo(BrandModel::class);
    }
}
