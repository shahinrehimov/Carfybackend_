<?php

namespace App\Models;

use App\Casts\Categories\ImageCast;
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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $is_active
 */
class Category extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, Translatable, InteractsWithMedia, CascadeSoftDeletes, SoftDeleteAcceptable;

    protected $fillable = [
        'uuid',
        'slug',
        'key', // nullable
        'parent_id',
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
    ];

    protected $casts = [
        'image' => ImageCast::class,
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'media',
        'subs',
    ];

    protected array $softDeleteAcceptableRelations = [
        'productRel',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('categories')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function scopeOnlyParents(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeOnlySubs(Builder $query): void
    {
        $query->whereNotNull('parent_id');
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function subs(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function mainProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'main_category_id', 'id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, ProductCategory::class, 'category_id', 'product_id')
            ->whereNull('product_categories.deleted_at');
    }

    public function productRel(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }
}

