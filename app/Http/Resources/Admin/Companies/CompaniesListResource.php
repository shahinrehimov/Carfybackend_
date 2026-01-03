<?php

namespace App\Http\Resources\Admin\Companies;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $logo
 * @method getLang(string $string)
 */
class CompaniesListResource extends JsonResource
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
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->getLang('title'),
            'logo' => $this->whenLoaded('media', function () {
                return $this->logo;
            }),
        ];
    }
}
