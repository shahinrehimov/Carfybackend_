<?php

namespace App\Models;

use App\Casts\CompanyProductVariants\DiscountCast;
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
 * @property mixed $company_id
 * @property mixed $product_variant_id
 * @property mixed $price
 * @property mixed $discount
 * @property mixed $discount_type
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @property mixed $stock
 * @property bool $is_active
 * @property mixed $approval_status
 * @property mixed|null $approval_status_message
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
}
