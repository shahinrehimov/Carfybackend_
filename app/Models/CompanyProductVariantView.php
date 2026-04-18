<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProductVariantView extends Model
{
    public $timestamps = false;

    protected $table = 'company_product_variant_views';

    protected $fillable = [
        'company_product_variant_id',
        'user_id',
        'guest_identifier',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    public function companyProductVariant(): BelongsTo
    {
        return $this->belongsTo(CompanyProductVariant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
