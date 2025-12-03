<?php

namespace App\Models;

use App\Casts\Socials\ImageCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Social extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'title',
        'url',
    ];

    protected $casts = [
        'image' => ImageCast::class,
    ];

    protected array $cascadeDeletes = ['media'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('socials')->singleFile();
    }
}
