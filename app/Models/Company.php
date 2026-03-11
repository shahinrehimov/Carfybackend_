<?php

namespace App\Models;

use App\Casts\Companies\BannerCast;
use App\Casts\Companies\LogoCast;
use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property bool $is_active
 * @property mixed $id
 * @property mixed $root_admin_email
 */
class Company extends Model implements TranslatableInterface, HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'uuid',
        'voen',
        'phone',
        'email',
        'city_id',
        'latitude',
        'longitude',
        'website',
        'root_admin_email',
        'verified_at',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
        'info',
        'address',
    ];

    protected $casts = [
        'tariff_expired_at' => 'datetime',
        'logo' => LogoCast::class,
        'banner' => BannerCast::class,
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'translatable',
        'media',
        'admins',
        'workingHours',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('companies_logo')->singleFile();
        $this->addMediaCollection('companies_banner')->singleFile();
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function admins(): HasMany
    {
        return $this->hasMany(CompanyAdmin::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(CompanyWorkingHour::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function productVariantListings(): HasMany
    {
        return $this->hasMany(CompanyProductVariant::class);
    }
}
