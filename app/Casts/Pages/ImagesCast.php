<?php

namespace App\Casts\Pages;

use App\Models\Page;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ImagesCast implements CastsAttributes
{
    public function get(Page|Model $model, string $key, mixed $value, array $attributes): array
    {
        $images = [];
        $ids = [];

        if ($model->hasMedia('pages_images')) {
            foreach ($model->getMedia('pages_images') as $media) {
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

    public function set(Model $model, string $key, mixed $value, array $attributes): bool
    {
        return false;
    }
}
