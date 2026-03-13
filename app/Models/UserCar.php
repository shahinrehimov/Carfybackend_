<?php

namespace App\Models;

use App\Casts\UserCars\ImageCast;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property mixed $user_id
 */
class UserCar extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'brand_model_id',
        'car_type_id',
        'engine_volume_id',
        'fuel_type_id',
        'transmission_type_id',
        'year',
        'engine_number',
        'vin_number',
    ];

    protected $casts = [
        'year'  => 'integer',
        'photo' => ImageCast::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('user_car_photos')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brandModel(): BelongsTo
    {
        return $this->belongsTo(BrandModel::class);
    }

    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
    }

    public function engineVolume(): BelongsTo
    {
        return $this->belongsTo(EngineVolume::class);
    }

    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class);
    }

    public function transmissionType(): BelongsTo
    {
        return $this->belongsTo(TransmissionType::class);
    }
}
