<?php

namespace App\Models;

use App\Casts\Sliders\ImageCast;
use App\Casts\Sliders\VideoCast;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\HasWebImageConversions;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property bool|mixed $is_active
 * @property mixed $slider_id
 */
class SliderLayer extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, InteractsWithMedia, CascadeSoftDeletes, HasWebImageConversions;

    protected $fillable = [
        'slider_id',
        'type', // background, image, text, button, video, custom
        'sort',
        'z_index',
        'position', // JSON: {x, y, width, height, unit: px|%|vw|vh}
        'mobile_position', // JSON: mobile specific positioning
        'animation', // JSON: {type, duration, delay, easing}
        'styles', // JSON: CSS styles
        'mobile_styles', // JSON: mobile specific styles
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
        'subtitle',
        'content',
        'button_text',
        'button_url',
        'alt_text',
    ];

    protected $casts = [
        'slider_id' => 'integer',
        'sort' => 'integer',
        'z_index' => 'integer',
        'position' => 'array',
        'mobile_position' => 'array',
        'animation' => 'array',
        'styles' => 'array',
        'mobile_styles' => 'array',
        'is_active' => 'boolean',
        'image' => ImageCast::class,
        'video' => VideoCast::class,
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'media',
    ];

    public function slider(): BelongsTo
    {
        return $this->belongsTo(Slider::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('slider_layer_images');
        $this->addMediaCollection('slider_layer_videos');
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        // Only images; videos in slider_layer_videos must not be converted.
        $this->registerWebImageConversions(['slider_layer_images']);
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
