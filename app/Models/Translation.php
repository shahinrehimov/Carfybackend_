<?php

namespace App\Models;

use App\Enum\TranslationGroupEnum;
use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Translation extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'group', // translation group enum
        'language_id',
        'key',
        'text',
    ];

    public function scopeForApi($query): void
    {
        $query->where('group', TranslationGroupEnum::APP);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
