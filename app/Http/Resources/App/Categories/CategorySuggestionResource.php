<?php

namespace App\Http\Resources\App\Categories;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $parent
 * @property int $products_count
 * @method getLang(string $column)
 */
class CategorySuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->getLang('title'),
            'slug'           => $this->slug,
            'is_sub'         => $this->parent !== null,
            'parent'         => $this->parent
                ? [
                    'title' => $this->parent->getLang('title'),
                    'slug'  => $this->parent->slug,
                ]
                : null,
            'products_count' => $this->products_count,
        ];
    }
}
