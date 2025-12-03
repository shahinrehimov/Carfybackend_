<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $used_count
 */
class Slug extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'model',
        'slug',
        'used_count',
    ];

    protected $casts = [
        'used_count' => 'integer',
    ];
}
