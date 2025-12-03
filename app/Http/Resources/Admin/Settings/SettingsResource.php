<?php

namespace App\Http\Resources\Admin\Settings;

use App\Enum\SettingTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $created_at
 * @property mixed $creatable
 * @property mixed $key
 * @property mixed $value
 * @property mixed $type
 * @property mixed $file
 */
class SettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */

    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'value' => $this->type === SettingTypeEnum::FILE->value ? $this->file : $this->value,
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
