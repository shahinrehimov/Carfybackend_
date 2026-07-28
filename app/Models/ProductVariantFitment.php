<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "this variant fits that car" rule. NULL means "all": a row with no brand
 * fits every car, a row with a brand but no model fits every model of that brand,
 * and an open year bound is unrestricted on that side.
 *
 * @property mixed $brand_id
 * @property mixed $brand_model_id
 * @property mixed $year_from
 * @property mixed $year_to
 */
class ProductVariantFitment extends Model
{
    protected $fillable = [
        'product_variant_id',
        'brand_id', // nullable = every brand
        'brand_model_id', // nullable = every model of the brand
        'year_from', // nullable = open-ended
        'year_to', // nullable = open-ended
    ];

    protected $casts = [
        'year_from' => 'integer',
        'year_to' => 'integer',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function brandModel(): BelongsTo
    {
        return $this->belongsTo(BrandModel::class);
    }

    /**
     * Fits every car regardless of brand — a rag, a cleaning spray, and so on.
     */
    public function isUniversal(): bool
    {
        return $this->brand_id === null;
    }
}
