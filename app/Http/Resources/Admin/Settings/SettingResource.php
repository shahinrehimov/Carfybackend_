<?php

namespace App\Http\Resources\Admin\Settings;

use App\Enum\SettingTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $key
 * @property mixed $value
 * @property mixed $type
 * @property mixed $file
 */
class SettingResource extends JsonResource
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
        ];
    }
}
