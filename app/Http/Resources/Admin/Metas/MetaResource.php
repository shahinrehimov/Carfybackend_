<?php

namespace App\Http\Resources\Admin\Metas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $translations
 * @property mixed $page_title
 * @property mixed $page_key
 */
class MetaResource extends JsonResource
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
            'page_title' => $this->page_title,
            'page_key' => $this->page_key,
            'translations' => $this->translations,
        ];
    }
}
