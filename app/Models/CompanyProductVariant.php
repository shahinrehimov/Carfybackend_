<?php

namespace App\Models;

use App\Casts\CompanyProductVariants\DiscountCast;
use App\Models\ProductReview;
use App\Casts\CompanyProductVariants\PriceCast;
use App\Casts\CompanyProductVariants\PriceFinalCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed       $company_id
 * @property mixed       $product_variant_id
 * @property mixed       $price
 * @property mixed       $discount
 * @property mixed       $discount_type
 * @property mixed       $discount_start_at
 * @property mixed       $discount_end_at
 * @property mixed       $stock
 * @property bool        $is_active
 * @property mixed       $approval_status
 * @property mixed|null  $approval_status_message
 * @property string|null $listing_hover_image
 * @property string|null $listing_main_image
 * @property array       $listing_gallery
 */
class CompanyProductVariant extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia, CascadeSoftDeletes;

    protected $table = 'company_product_variant';

    protected $fillable = [
        'uuid',
        'company_id',
        'product_variant_id',
        'stock',
        'price',
        'discount_type',
        'discount',
        'discount_start_at',
        'discount_end_at',
        'city_id',
        'is_active',
        'approval_status',
        'approval_status_message',
    ];

    protected $casts = [
        'price' => PriceCast::class,
        'price_final' => PriceFinalCast::class,
        'discount' => DiscountCast::class,
        'discount_start_at' => 'datetime',
        'discount_end_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'media',
        'approvalStatuses',
        'baskets',
        'wishlists',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('company_product_variant_images');
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function approvalStatuses(): HasMany
    {
        return $this->hasMany(ProductVariantApprovalStatus::class);
    }

    public function baskets(): HasMany
    {
        return $this->hasMany(Basket::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    // ─── Image cascade accessors ──────────────────────────────────────────────

    /**
     * Hover image: CPV images[0] → variant hover_image → product hover_image
     */
    public function getListingHoverImageAttribute(): ?string
    {
        if ($this->relationLoaded('media') && $this->hasMedia('company_product_variant_images')) {
            return $this->getFirstMediaUrl('company_product_variant_images');
        }

        if ($this->relationLoaded('productVariant') && $this->productVariant) {
            $variant = $this->productVariant;

            if ($variant->relationLoaded('media') && $variant->hover_image) {
                return $variant->hover_image;
            }

            if ($variant->relationLoaded('product') && $variant->product?->relationLoaded('media')) {
                return $variant->product->hover_image;
            }
        }

        return null;
    }

    /**
     * Main image: CPV images[0] → variant main_image → product main_image
     */
    public function getListingMainImageAttribute(): ?string
    {
        if ($this->relationLoaded('media') && $this->hasMedia('company_product_variant_images')) {
            return $this->getFirstMediaUrl('company_product_variant_images');
        }

        if ($this->relationLoaded('productVariant') && $this->productVariant) {
            $variant = $this->productVariant;

            if ($variant->relationLoaded('media') && $variant->main_image) {
                return $variant->main_image;
            }

            if ($variant->relationLoaded('product') && $variant->product?->relationLoaded('media')) {
                return $variant->product->main_image;
            }
        }

        return null;
    }

    /**
     * Gallery: CPV images + variant images + product images (CPV önce)
     * Her element: ['source' => 'seller'|'variant'|'product', 'url' => '...']
     */
    public function getListingGalleryAttribute(): array
    {
        $gallery = [];

        if ($this->relationLoaded('media')) {
            foreach ($this->getMedia('company_product_variant_images') as $media) {
                $gallery[] = ['source' => 'seller', 'url' => $media->getUrl()];
            }
        }

        if ($this->relationLoaded('productVariant') && $this->productVariant) {
            $variant = $this->productVariant;

            if ($variant->relationLoaded('media')) {
                foreach ($variant->getMedia('product_variants_images') as $media) {
                    $gallery[] = ['source' => 'variant', 'url' => $media->getUrl()];
                }
            }

            if ($variant->relationLoaded('product') && $variant->product?->relationLoaded('media')) {
                foreach ($variant->product->getMedia('products_images') as $media) {
                    $gallery[] = ['source' => 'product', 'url' => $media->getUrl()];
                }
            }
        }

        return $gallery;
    }
}
