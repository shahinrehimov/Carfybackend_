<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wishlist extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'userable_type',
        'userable_id',
        'product_id',
        'product_variant_id',
        'company_product_variant_id',
    ];

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

    public function user(): MorphTo
    {
        return $this->morphTo('userable');
    }
}
