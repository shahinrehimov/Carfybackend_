<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariantApprovalStatus extends Model
{
    use SoftDeletes, ActionBy, ActionUser;

    protected $fillable = [
        'product_variant_id',
        'company_product_variant_id',
        'approval_status', // ProductVariantApprovalStatusEnum
        'approval_status_message', // nullable, max 3000
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function companyProductVariant(): BelongsTo
    {
        return $this->belongsTo(CompanyProductVariant::class);
    }
}
