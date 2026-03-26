<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use App\Traits\SoftDeleteAcceptable;
use App\Traits\Translatable;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $country_id
 * @property mixed $is_active
 */
class City extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, CascadeSoftDeletes, SoftDeleteAcceptable;

    protected $fillable = [
        'country_id',
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
    ];

    protected $casts = [
        'sort' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $cascadeDeletes = [
        'translatable',
    ];

    protected array $softDeleteAcceptableRelations = [
        'addresses',
        'companies',
        'productVariants',
    ];

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function productVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function companyProductVariants(): HasMany
    {
        return $this->hasMany(CompanyProductVariant::class);
    }
}
