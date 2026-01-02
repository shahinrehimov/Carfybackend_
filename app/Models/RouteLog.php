<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RouteLog extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = ['url', 'url_full', 'ip', 'method', 'action', 'requests', 'company_id'];
}
