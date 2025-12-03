<?php

namespace App\Http\Resources\App\Sliders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $type
 * @property mixed $position
 * @property mixed $mobile_position
 * @property mixed $animation
 * @property mixed $styles
 * @property mixed $mobile_styles
 * @property mixed $z_index
 * @property mixed $image
 * @property mixed $video
 * @method getLang(string $string)
 */
class SliderLayersResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'position' => $this->position,
            'mobile_position' => $this->mobile_position,
            'animation' => $this->animation,
            'styles' => $this->styles,
            'mobile_styles' => $this->mobile_styles,
            'z_index' => $this->z_index,
            'title' => $this->getLang('title'),
            'subtitle' => $this->getLang('subtitle'),
            'content' => $this->getLang('content'),
            'button_text' => $this->getLang('button_text'),
            'button_url' => $this->getLang('button_url'),
            'alt_text' => $this->getLang('alt_text'),
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'video' => $this->whenLoaded('media', function () {
                return $this->video;
            }),
        ];
    }
}
