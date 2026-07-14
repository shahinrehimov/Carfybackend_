<?php

namespace App\Traits;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Registers optimized web image conversions for product/variant media.
 *
 * The original upload is always kept untouched. Two WebP conversions are
 * generated and served instead of the original:
 *  - "web":   downscaled to fit within 1600x1600 (never upscaled, aspect kept)
 *  - "thumb": downscaled to fit within 400x400 for listings/grids
 *
 * WebP quality is tuned to the "no visible loss" sweet spot. Conversions run
 * through the optimizer chain configured in config/media-library.php.
 */
trait HasWebImageConversions
{
    /**
     * @param  string[]  $collections  Collections these conversions apply to.
     */
    public function registerWebImageConversions(array $collections): void
    {
        $this->addMediaConversion('web')
            ->fit(Fit::Max, 1600, 1600)
            ->format('webp')
            ->quality(82)
            ->performOnCollections(...$collections)
            ->nonQueued();

        $this->addMediaConversion('thumb')
            ->fit(Fit::Max, 400, 400)
            ->format('webp')
            ->quality(80)
            ->performOnCollections(...$collections)
            ->nonQueued();
    }

    /**
     * URL of the optimized "web" conversion for the first media in a collection,
     * falling back to the original if the conversion has not been generated yet.
     */
    public function getWebImageUrl(string $collection, string $conversion = 'web'): ?string
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }

    /**
     * URL of the optimized conversion for a specific media item, falling back
     * to the original when the conversion is not yet available.
     */
    public static function webUrlForMedia(Media $media, string $conversion = 'web'): string
    {
        return $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
}
