<?php

namespace App\Models;

use App\Casts\Orders\AmountCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'order_id',
        'basket_id',
        'product_id',
        'product_variant_id',
        'company_product_variant_id',
        'amount',
        'amount_final',
        'is_set',
        'quantity',
        'sets', // [id, quantity]
    ];

    protected $casts = [
        'amount' => AmountCast::class,
        'amount_final' => AmountCast::class,
        'is_set' => 'boolean',
        'quantity' => 'integer',
        'sets' => 'array',
    ];

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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function basket(): HasOne
    {
        return $this->hasOne(Basket::class);
    }
}
