<?php

namespace App\Models;

use App\Casts\Orders\AmountCast;
use App\Enum\OrderItemStatusEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'order_id',
        'basket_id',
        'product_id',
        'product_variant_id',
        'company_product_variant_id',
        'amount',
        'amount_final',
        'commission_percent',
        'commission_amount',
        'is_set',
        'quantity',
        'sets', // [id, quantity]
        'selected_brand_id', // nullable, the car the customer bought the part for
        'selected_brand_model_id', // nullable
        'status',
        'cancel_reason',
        'cancelled_by_type',
        'cancelled_by_id',
    ];

    protected $casts = [
        'amount' => AmountCast::class,
        'amount_final' => AmountCast::class,
        // Platform commission frozen when the order was placed
        'commission_percent' => 'float',
        'commission_amount' => AmountCast::class,
        'is_set' => 'boolean',
        'quantity' => 'integer',
        'sets' => 'array',
        'status' => OrderItemStatusEnum::class,
    ];

    protected array $cascadeDeletes = ['itemStatuses'];

    public function scopeIsSet(Builder $builder): void
    {
        $builder->where('is_set', true);
    }

    public function scopeIsNotSet(Builder $builder): void
    {
        $builder->where('is_set', false);
    }

    public function isSet()
    {
        return $this->is_set ?? false;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function companyProductVariant(): BelongsTo
    {
        return $this->belongsTo(CompanyProductVariant::class);
    }

    /**
     * Soft-deleted brands still resolve: the choice is part of what was bought.
     */
    public function selectedBrand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'selected_brand_id')->withTrashed();
    }

    public function selectedBrandModel(): BelongsTo
    {
        return $this->belongsTo(BrandModel::class, 'selected_brand_model_id')->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class);
    }

    public function itemStatuses(): HasMany
    {
        return $this->hasMany(OrderItemStatus::class)->orderBy('id');
    }

    public function isCanceled(): bool
    {
        return $this->status === OrderItemStatusEnum::CANCELED;
    }

    public function isActive(): bool
    {
        return $this->status !== OrderItemStatusEnum::CANCELED;
    }
}
