<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantProductOption extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, SoftDeletes, SlugTrait;

    protected $fillable = [
        'product_variant_id',
        'product_option_id',
        'product_option_item_id', // for hasItem is true
        'value', // for hasItem is false and translatable is false
        'slug',
    ];

    protected array $translatableFields = [
        'translatable_value',
    ];

    protected $casts = [
        'product_variant_id' => 'integer',
        'product_option_id' => 'integer',
        'product_option_item_id' => 'integer',
    ];

    protected array $cascadeDeletes = [
        'translatable',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductOptionItem::class, 'product_option_item_id');
    }
}
