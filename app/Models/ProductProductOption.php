<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductProductOption extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, SoftDeletes;

    protected $fillable = [
        'product_id',
        'product_option_id',
        'product_option_item_id', // for hasItem is true
        'value', // for hasItem is false and translatable is false
    ];

    protected array $translatableFields = [
        'translatable_value',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_option_id' => 'integer',
        'product_option_item_id' => 'integer',
    ];

    protected array $cascadeDeletes = [
        'translatable',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
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
