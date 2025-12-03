<?php

namespace App\Models;

use App\Casts\Admins\FullNameCast;
use App\Casts\Admins\ImageCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property mixed $token
 * @property mixed $id
 * @property mixed $email
 * @property mixed $password
 * @property mixed $name
 * @property mixed $surname
 * @method createToken($device_type, string[] $array)
 */
class Admin extends Authenticatable implements HasMedia
{
    use HasApiTokens, Notifiable, HasRoles, SoftDeletes, ActionBy, ActionUser, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'surname',
        'phone', // nullable
    ];

    protected $casts = [
        'password' => 'hashed',
        'image' => ImageCast::class,
        'full_name' => FullNameCast::class,
    ];

    protected $hidden = [
        'password',
    ];

    protected array $cascadeDeletes = [
        'media',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('admins')->singleFile();
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'approved_by');
    }
}
