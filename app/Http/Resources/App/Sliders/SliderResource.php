<?php

namespace App\Http\Resources\App\Sliders;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $type
 * @property mixed $height
 * @property mixed $mobile_height
 * @property mixed $auto_play_interval
 * @property mixed $show_navigation
 * @property mixed $settings
 * @property mixed $transition_duration
 * @property mixed $auto_play
 * @property mixed $show_pagination
 * @property mixed $show_progress_bar
 * @property mixed $transition_effect
 * @method getLang(string $string)
 */
class SliderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->getLang('title'),
            'description' => $this->getLang('description'),
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
            'layers' => SliderLayersResource::collection($this->whenLoaded('layers')),
        ];
    }
}
