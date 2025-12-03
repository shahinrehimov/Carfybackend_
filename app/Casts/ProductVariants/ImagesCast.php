<?php

namespace App\Casts\ProductVariants;

use App\Models\ProductVariant;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImagesCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param ProductVariant|Model $model
     * @param string $key
     * @param mixed $value
     * @param array $attributes
     * @return array
     */
    public function get(ProductVariant|Model $model, string $key, mixed $value, array $attributes): array
    {
        $images = [];
        $ids = [];

        if ($model->hasMedia('product_variants_images')) {
            foreach ($model->getMedia('product_variants_images') as $media) {
                /** @var Media $media */
                $ids[] = $media->getQueueableId();
                $images[] = $media->getUrl();
            }
        }

        return [
            'urls' => $images,
            'media_ids' => $ids,
        ];
    }

    /**
     * Prepare the given value for storage.
     *
     * @param Model $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return bool
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
