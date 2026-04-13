<?php

namespace App\Models;

use App\Interfaces\TranslatableInterface;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use App\Traits\SoftDeleteAcceptable;
use App\Traits\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Backed by product_option_items table, filtered to the 'color' option.
 * hex_code is stored inside the details JSON column.
 *
 * @property mixed $id
 * @property string|null $hex_code
 * @property mixed $sort
 * @property mixed $is_active
 * @property mixed $product_option_id
 */
class CarColor extends Model implements TranslatableInterface
{
    use SoftDeletes, ActionBy, ActionUser, Translatable, CascadeSoftDeletes, SoftDeleteAcceptable;

    protected $table = 'product_option_items';

    protected $fillable = [
        'product_option_id',
        'uuid',
        'slug',
        'details',
        'sort',
        'is_active',
    ];

    protected array $translatableFields = [
        'title',
    ];

    protected $casts = [
        'sort'      => 'integer',
        'is_active' => 'boolean',
        'details'   => 'array',
    ];

    protected array $cascadeDeletes = [
        'translatable',
    ];

    protected array $softDeleteAcceptableRelations = [
        'userCars',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('color_option', function (Builder $builder) {
            $colorOptionId = static::getColorOptionId();
            if ($colorOptionId) {
                $builder->where('product_option_items.product_option_id', $colorOptionId);
            }
        });

        static::creating(function (CarColor $model) {
            if (!$model->product_option_id) {
                $model->product_option_id = static::getColorOptionId();
            }
            if (!$model->uuid) {
                $model->uuid = Str::uuid();
            }
        });
    }

    private static ?int $colorOptionIdCache = null;

    public static function getColorOptionId(): ?int
    {
        if (static::$colorOptionIdCache === null) {
            static::$colorOptionIdCache = ProductOption::query()
                ->where('name', 'color')
                ->value('id');
        }

        return static::$colorOptionIdCache;
    }

    /**
     * hex_code is stored in details JSON as details['hex_code'].
     */
    protected function hexCode(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->details['hex_code'] ?? null,
            set: function (string $value) {
                $this->details = array_merge($this->details ?? [], ['hex_code' => $value]);
            },
        );
    }

    public function scopeActive(Builder $builder): void
    {
        $builder->where('is_active', true);
    }

    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function userCars(): HasMany
    {
        return $this->hasMany(UserCar::class, 'car_color_id');
    }
}
