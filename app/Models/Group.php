<?php

namespace App\Models;

use App\Casts\Groups\ImageCast;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property bool|mixed $is_active
 * @property mixed $products_data
 */
class Group extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, Translatable, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'uuid',
        'name', // unique
        'slug',
        'max_count', // null for unlimited
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
        'description',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
        'max_count' => 'integer',
        'image' => ImageCast::class,
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'productsRel',
        'media',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('groups')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, GroupProduct::class)
            ->whereNull('group_products.deleted_at');
    }

    public function productsRel(): HasMany
    {
        return $this->hasMany(GroupProduct::class);
    }

    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, GroupProduct::class)
            ->whereNull('group_products.deleted_at');
    }
}
