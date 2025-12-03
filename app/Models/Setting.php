<?php

namespace App\Models;

use App\Casts\Settings\FileCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $for_system
 * @property mixed $type
 */
class Setting extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'type',
        'key',
        'value',
        'for_system',
    ];

    protected $casts = [
        'file' => FileCast::class,
        'for_system' => 'boolean',
    ];

    protected array $cascadeDeletes = ['media'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('settings')->singleFile();
    }

    public function scopeForSystem($query): void
    {
        $query->where('for_system', true);
    }

    public function isForSystem()
    {
        return $this->for_system;
    }
}
