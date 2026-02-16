<?php

namespace App\Models;

use App\Casts\Brands\ImageCast;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\SoftDeleteAcceptable;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property bool|mixed $is_active
 */
class Brand extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, Translatable, InteractsWithMedia, CascadeSoftDeletes, SoftDeleteAcceptable;

    protected $fillable = [
        'uuid',
        'slug',
        'key', // nullable
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
    ];

    protected array $softDeleteAcceptableRelations = [
        'products',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('brands')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function brandModels(): HasMany
    {
        return $this->hasMany(BrandModel::class);
    }
}
