<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerSearchToken extends Model
{
    public $timestamps = false;

    protected $table = 'seller_search_tokens';

    protected $fillable = [
        'uuid',
        'company_id',
        'admin_id',
        'created_at',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
