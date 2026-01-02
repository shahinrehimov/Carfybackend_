<?php

namespace App\Models;

use App\Casts\CompanyAdmins\ImageCast;
use App\Notifications\CompanyAdminResetPasswordNotification;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $email
 * @property mixed $company_id
 * @property mixed|string $token
 * @property bool|mixed $is_active
 * @property mixed $password_must_change
 * @property mixed $password
 */
class CompanyAdmin extends Authenticatable implements HasMedia
{
    use HasApiTokens, Notifiable, SoftDeletes, ActionBy, ActionUser, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'company_id',
        'is_root',
        'password_must_change',
        'username',
        'email',
        'password',
        'name',
        'surname',
        'is_active',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_root' => 'boolean',
        'password_must_change' => 'boolean',
        'is_active' => 'boolean',
        'image' => ImageCast::class,
    ];

    protected $hidden = [
        'password',
    ];

    protected array $cascadeDeletes = [
        'media',
    ];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CompanyAdminResetPasswordNotification($token));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('company_admins')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function scopeRoot(Builder $builder): void
    {
        $builder->where('is_root', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function isRoot(): bool
    {
        return $this->is_root ?? false;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
