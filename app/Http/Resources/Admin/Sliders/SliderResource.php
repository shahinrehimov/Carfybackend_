<?php

namespace App\Http\Resources\Admin\Sliders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $sort
 * @property mixed $type
 * @property mixed $height
 * @property mixed $mobile_height
 * @property mixed $auto_play
 * @property mixed $auto_play_interval
 * @property mixed $show_navigation
 * @property mixed $show_pagination
 * @property mixed $show_progress_bar
 * @property mixed $transition_effect
 * @property mixed $transition_duration
 * @property mixed $settings
 * @property mixed $translations
 * @property mixed $created_at
 * @method isActive()
 */
class SliderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'sort' => $this->sort,
            'type' => $this->type,
            'height' => $this->height,
            'mobile_height' => $this->mobile_height,
            'auto_play' => $this->auto_play,
            'auto_play_interval' => $this->auto_play_interval,
            'show_navigation' => $this->show_navigation,
            'show_pagination' => $this->show_pagination,
            'show_progress_bar' => $this->show_progress_bar,
            'transition_effect' => $this->transition_effect,
            'transition_duration' => $this->transition_duration,
            'settings' => $this->settings,
            'is_active' => $this->isActive(),
            'translations' => $this->translations,
            'layers' => SliderLayerResource::collection($this->whenLoaded('layers')),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
