<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CompanyWorkingHour extends Model implements HasMedia
{
    use SoftDeletes, ActionBy, ActionUser, InteractsWithMedia, CascadeSoftDeletes;

    protected $fillable = [
        'company_id',
        'day_of_week',
        'is_closed',
        'open_time', // nullable
        'close_time', // nullable
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
