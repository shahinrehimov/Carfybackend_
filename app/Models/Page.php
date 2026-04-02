<?php

namespace App\Models;

use App\Casts\Pages\IconCast;
use App\Casts\Pages\ImagesCast;
use App\Casts\Pages\MainImageCast;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property bool|mixed $is_active
 */
class Page extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, Translatable, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'slug',
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
        'description',
        'body',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
        'main_image' => MainImageCast::class,
        'icon' => IconCast::class,
        'images' => ImagesCast::class,
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'media',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pages_main')->singleFile();
        $this->addMediaCollection('pages_icon')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }
}
