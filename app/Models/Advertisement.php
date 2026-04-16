<?php

namespace App\Models;

use App\Enum\AdvertisementPlatformEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Carbon\Carbon;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $title
 * @property string $key
 * @property string|null $description
 * @property string $link
 * @property string $start_date
 * @property string $end_date
 * @property bool $is_active
 * @property string $platform
 * @property int $click_count
 */
class Advertisement extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'title',
        'key',
        'description',
        'link',
        'start_date',
        'end_date',
        'is_active',
        'platform',
        'click_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'click_count' => 'integer',
    ];

    protected array $cascadeDeletes = [
        'media',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('advertisement_image')->singleFile();
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdvertisementClick::class);
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = Carbon::today();

        return $this->start_date <= $today && $this->end_date >= $today;
    }

    public function scopeActive(Builder $builder): void
    {
        $today = Carbon::today()->toDateString();

        $builder->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today);
    }

    public function scopeForPlatform(Builder $builder, string $platform): void
    {
        $builder->where(function (Builder $q) use ($platform) {
            $q->where('platform', $platform)
                ->orWhere('platform', AdvertisementPlatformEnum::BOTH->value);
        });
    }
}
