<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SoftDeleteAcceptable;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool|mixed $is_active
 * @property mixed $id
 * @property mixed|string $name
 * @property mixed $items
 */
class ProductOption extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, CascadeSoftDeletes, SoftDeleteAcceptable;

    protected $fillable = [
        'uuid',
        'name', // unique
        'is_required',
        'has_items',
        'is_translatable',
        'can_multiple',
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
    ];

    protected array $softDeleteAcceptableRelations = [
        'productVariants',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'has_items' => 'boolean',
        'is_translatable' => 'boolean',
        'can_multiple' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'items',
    ];

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function scopeIsRequired(Builder $builder): void
    {
        $builder->where('is_required', true);
    }

    public function scopeHasItems(Builder $builder): void
    {
        $builder->where('has_items', true);
    }

    public function scopeIsTranslatable(Builder $builder): void
    {
        $builder->where('is_translatable', true);
    }

    public function scopeCanMultiple(Builder $builder): void
    {
        $builder->where('can_multiple', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function isRequired(): bool
    {
        return $this->is_required ?? false;
    }

    public function hasItems(): bool
    {
        return $this->has_items ?? false;
    }

    public function isTranslatable(): bool
    {
        return $this->is_translatable ?? false;
    }

    public function canMultiple(): bool
    {
        return $this->can_multiple ?? false;
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOptionItem::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariantProductOption::class, 'product_option_id');
    }
}
