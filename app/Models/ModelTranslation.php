<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModelTranslation extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'language_id',
        'column',
        'text',
    ];

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
