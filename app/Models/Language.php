<?php

namespace App\Models;

use App\Casts\Languages\KeyCast;
use App\Services\LangService;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Dyrynda\Database\Support\CascadeSoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $is_active
 */
class Language extends Model
{
    use SoftDeletes, ActionBy, ActionUser, CascadeSoftDeletes;

    protected $fillable = [
        'key', // len 2
        'title',
    ];

    protected $casts = [
        'key' => KeyCast::class,
    ];

    protected array $cascadeDeletes = ['translations', 'model_translations'];

    public static function boot(): void
    {
        parent::boot();
        static::saved(static function () {
            LangService::instance()->setLanguagesCache();
        });
        static::deleted(static function () {
            LangService::instance()->setLanguagesCache();
        });
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function model_translations(): HasMany
    {
        return $this->hasMany(ModelTranslation::class);
    }
}
