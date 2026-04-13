<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\SoftDeleteAcceptable;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $product_option_id
 * @property bool|mixed $is_active
 */
class ProductOptionItem extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, SlugTrait, CascadeSoftDeletes, SoftDeleteAcceptable;

    protected $fillable = [
        'uuid',
        'slug',
        'key', // nullable
        'details', // nullable JSON, option-specific metadata (e.g. hex_code for color)
        'product_option_id',
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
    ];

    protected $casts = [
        'sort'              => 'integer',
        'is_active'         => 'boolean',
        'product_option_id' => 'integer',
        'details'           => 'array',
    ];

    protected array $cascadeDeletes = [
        'translatable',
    ];

    protected array $softDeleteAcceptableRelations = [
        'productVariants',
    ];

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function productOption(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariantProductOption::class, 'product_option_item_id');
    }
}
