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
use App\Traits\HasWebImageConversions;
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
 * @property Collection|mixed $fitments
 * @property bool $is_universal
 * @property bool $requires_fitment_selection
 * @property int|null $year_from
 * @property int|null $year_to
 * @property int|null $year deprecated, use year_from / year_to
 */

class ProductVariant extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, InteractsWithMedia, CascadeSoftDeletes, HasWebImageConversions;

    protected $fillable = [
        'company_id', // nullable
        'uuid',
        'is_set',
        'is_default',
        'slug', // nullable, unique
        'product_id',
        'oem_code', // nullable
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
        'requires_fitment_selection', // customer picks the car brand/model when buying
    ];

    protected $casts = [
        'is_set' => 'boolean',
        'requires_fitment_selection' => 'boolean',
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

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->registerWebImageConversions(['product_variants_main', 'product_variants_hover']);
    }

    public static function getPriceCalculationQuery(): string
    {
        $now = Carbon::now()->toDateTimeString();

        return "
                COALESCE((
                    SELECT MIN(
                        ROUND(
                            CASE
                                WHEN cpv.discount IS NULL
                                    OR cpv.discount = 0
                                    OR cpv.discount_type IS NULL
                                    OR (cpv.discount_start_at IS NOT NULL AND cpv.discount_start_at > '{$now}')
                                    OR (cpv.discount_end_at IS NOT NULL AND cpv.discount_end_at < '{$now}')
                                THEN cpv.price / 100.0
                                ELSE
                                    CASE
                                        WHEN cpv.discount_type = 'by_percentage'
                                        THEN (cpv.price - (cpv.price * cpv.discount / 100.0)) / 100.0
                                        WHEN cpv.discount_type = 'by_amount'
                                        THEN (cpv.price - cpv.discount) / 100.0
                                        ELSE cpv.price / 100.0
                                    END
                            END
                        , 2)
                    )
                    FROM company_product_variant cpv
                    WHERE cpv.product_variant_id = product_variants.id
                    AND cpv.is_active = true
                    AND cpv.approval_status = 'approved'
                    AND cpv.deleted_at IS NULL
                ), 0)
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

    public function fitments(): HasMany
    {
        return $this->hasMany(ProductVariantFitment::class);
    }

    /**
     * The concrete models this variant fits. Universal and brand-wide fitment rows
     * name no model, so they are absent here by design — read fitments for those.
     * Kept so existing resources keep resolving while clients migrate.
     */
    public function brandModels(): BelongsToMany
    {
        return $this->belongsToMany(BrandModel::class, 'product_variant_fitments');
    }

    /**
     * Fits every car, whatever the brand.
     */
    public function getIsUniversalAttribute(): bool
    {
        return $this->fitments->contains(fn (ProductVariantFitment $fitment) => $fitment->brand_id === null);
    }

    /**
     * Earliest covered year, or null when any fitment is open-ended on that side.
     */
    public function getYearFromAttribute(): ?int
    {
        return $this->yearBound('year_from', 'min');
    }

    /**
     * Latest covered year, or null when any fitment is open-ended on that side.
     */
    public function getYearToAttribute(): ?int
    {
        return $this->yearBound('year_to', 'max');
    }

    /**
     * Legacy single-year attribute, kept so app and site responses do not break
     * while they move to year_from / year_to. Deprecated.
     */
    public function getYearAttribute(): ?int
    {
        return $this->year_from;
    }

    private function yearBound(string $column, string $aggregate): ?int
    {
        $fitments = $this->fitments;

        // One open-ended row makes the whole variant unbounded on that side.
        if ($fitments->isEmpty() || $fitments->contains(fn ($fitment) => $fitment->{$column} === null)) {
            return null;
        }

        return (int) $fitments->{$aggregate}($column);
    }

    /**
     * Listings that fit any of the given brands, plus universal ones.
     */
    public function scopeFitsBrands(Builder $builder, array $brandIds): void
    {
        if (! $brandIds) {
            return;
        }

        $builder->whereHas('fitments', fn (Builder $query) => $query->where(
            fn (Builder $inner) => $inner->whereIn('brand_id', $brandIds)->orWhereNull('brand_id')
        ));
    }

    /**
     * Listings that fit any of the given models, plus the wildcard rows covering
     * them — "every model of this brand" and "every car".
     */
    public function scopeFitsModels(Builder $builder, array $modelIds): void
    {
        if (! $modelIds) {
            return;
        }

        $brandIds = BrandModel::query()->whereKey($modelIds)->pluck('brand_id')->unique()->all();

        $builder->whereHas('fitments', fn (Builder $query) => $query->where(
            fn (Builder $inner) => $inner
                ->whereIn('brand_model_id', $modelIds)
                ->orWhere(fn (Builder $wildcard) => $wildcard
                    ->whereNull('brand_model_id')
                    ->where(fn (Builder $scope) => $scope
                        ->whereNull('brand_id')
                        ->orWhereIn('brand_id', $brandIds)))
        ));
    }

    /**
     * Listings whose covered years overlap the requested range.
     */
    public function scopeFitsYearRange(Builder $builder, ?int $min, ?int $max): void
    {
        if ($min === null && $max === null) {
            return;
        }

        $builder->whereHas('fitments', function (Builder $query) use ($min, $max) {
            if ($max !== null) {
                $query->where(fn (Builder $q) => $q->whereNull('year_from')->orWhere('year_from', '<=', $max));
            }

            if ($min !== null) {
                $query->where(fn (Builder $q) => $q->whereNull('year_to')->orWhere('year_to', '>=', $min));
            }
        });
    }

    /**
     * Listings fitting one specific car. Model and year are matched against the
     * same fitment row, so a variant fitting an X5 2012-2017 and an X3 2018-2022
     * is not offered for an X5 2020.
     */
    public function scopeFitsCar(Builder $builder, ?int $brandModelId, ?int $year): void
    {
        if ($brandModelId === null) {
            return;
        }

        $brandId = BrandModel::query()->whereKey($brandModelId)->value('brand_id');

        $builder->whereHas('fitments', function (Builder $query) use ($brandModelId, $brandId, $year) {
            $query->where(fn (Builder $inner) => $inner
                ->where('brand_model_id', $brandModelId)
                ->orWhere(fn (Builder $wildcard) => $wildcard
                    ->whereNull('brand_model_id')
                    ->where(fn (Builder $scope) => $scope
                        ->whereNull('brand_id')
                        ->orWhere('brand_id', $brandId))));

            if ($year !== null) {
                $query->where(fn (Builder $q) => $q->whereNull('year_from')->orWhere('year_from', '<=', $year))
                    ->where(fn (Builder $q) => $q->whereNull('year_to')->orWhere('year_to', '>=', $year));
            }
        });
    }

    public function companyListings(): HasMany
    {
        return $this->hasMany(CompanyProductVariant::class);
    }

    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_product_variant')
            ->withPivot(['uuid', 'stock', 'price', 'discount_type', 'discount', 'discount_start_at', 'discount_end_at', 'city_id', 'is_active', 'approval_status', 'approval_status_message'])
            ->withTimestamps();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductVariantRevision::class);
    }

    public function pendingRevision(): HasMany
    {
        return $this->hasMany(ProductVariantRevision::class)->where('status', 'pending');
    }
}
