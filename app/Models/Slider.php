<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SlugTrait;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool|mixed $is_active
 * @property mixed $auto_play
 * @property mixed $show_navigation
 * @property mixed $show_pagination
 */

class Slider extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, SlugTrait, Translatable, CascadeSoftDeletes;

    protected $fillable = [
        'slug',
        'sort',
        'type', // hero, banner, gallery, custom
        'height', // desktop height in px
        'mobile_height', // mobile height in px
        'auto_play',
        'auto_play_interval', // milliseconds
        'show_navigation',
        'show_pagination',
        'show_progress_bar',
        'transition_effect', // slide, fade, zoom, parallax
        'transition_duration', // milliseconds
        'settings', // JSON field for additional settings
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
        'description',
    ];

    protected $casts = [
        'sort' => 'integer',
        'height' => 'integer',
        'mobile_height' => 'integer',
        'auto_play' => 'boolean',
        'auto_play_interval' => 'integer',
        'show_navigation' => 'boolean',
        'show_pagination' => 'boolean',
        'show_progress_bar' => 'boolean',
        'transition_duration' => 'integer',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'layers',
    ];

    public function layers(): HasMany
    {
        return $this->hasMany(SliderLayer::class)->orderBy('z_index')->orderBy('sort');
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function scopeByType(Builder $builder, string $type): void
    {
        $builder->where('type', $type);
    }
}
