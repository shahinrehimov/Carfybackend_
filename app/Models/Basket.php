<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $sets
 * @property mixed $quantity
 */
class Basket extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'userable_type',
        'userable_id',
        'product_id',
        'product_variant_id',
        'is_set',
        'quantity',
        'sets', // [id, quantity]
    ];

    protected $casts = [
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

    public function user(): MorphTo
    {
        return $this->morphTo('userable');
    }
}
