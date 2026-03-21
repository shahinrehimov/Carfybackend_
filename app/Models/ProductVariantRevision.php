<?php

namespace App\Models;

use App\Traits\ActionBy;
use App\Traits\ActionUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariantRevision extends Model implements HasMedia
{
    use ActionBy, ActionUser, InteractsWithMedia;

    protected $fillable = [
        'product_variant_id',
        'company_id',
        'status',
        'rejection_message',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('revision_main_image')->singleFile();
        $this->addMediaCollection('revision_hover_image')->singleFile();
        $this->addMediaCollection('revision_images');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
