<?php

namespace App\Casts\ProductReviews;

use App\Models\ProductReview;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImagesCast implements CastsAttributes
{
    /**
     * @param ProductReview|Model $model
     */
    public function get(ProductReview|Model $model, string $key, mixed $value, array $attributes): array
    {
        $images = [];
        $ids = [];

        if ($model->hasMedia('product_review_images')) {
            foreach ($model->getMedia('product_review_images') as $media) {
                /** @var Media $media */
                $ids[] = $media->getQueueableId();
                $images[] = ProductReview::webUrlForMedia($media);
            }
        }

        return [
            'urls'      => $images,
            'media_ids' => $ids,
        ];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
