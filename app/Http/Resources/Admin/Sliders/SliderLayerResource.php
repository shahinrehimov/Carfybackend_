<?php

namespace App\Http\Resources\Admin\Sliders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slider_id
 * @property mixed $type
 * @property mixed $sort
 * @property mixed $z_index
 * @property mixed $position
 * @property mixed $mobile_position
 * @property mixed $animation
 * @property mixed $styles
 * @property mixed $mobile_styles
 * @property mixed $translations
 * @property mixed $image
 * @property mixed $created_at
 * @property mixed $video
 * @method isActive()
 */
class SliderLayerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slider_id' => $this->slider_id,
            'type' => $this->type,
            'sort' => $this->sort,
            'z_index' => $this->z_index,
            'position' => $this->position,
            'mobile_position' => $this->mobile_position,
            'animation' => $this->animation,
            'styles' => $this->styles,
            'mobile_styles' => $this->mobile_styles,
            'is_active' => $this->isActive(),
            'translations' => $this->translations,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'video' => $this->whenLoaded('media', function () {
                return $this->video;
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
