<?php

namespace App\Http\Resources\Admin\ProductVariants;

use App\Http\Resources\Admin\Brands\BrandsListResource;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Admin\Products\ProductsResource;
use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $created_at
 * @property mixed $id
 * @property mixed $creatable
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $main_image
 * @property mixed $hover_image
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @property mixed $discount_type
 * @property mixed $discount
 * @method getLang(string $string)
 * @method isActive()
 * @method isSet()
 * @method isDefault()
 */
class ProductVariantsResource extends JsonResource
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
            'slug' => $this->slug,
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'product' => ProductsResource::make($this->whenLoaded('product')),
            'is_set' => $this->isSet(),
            'sets_count' => $this->whenCounted('sets'),
            'groups' => GroupsListResource::collection($this->whenLoaded('groups')),
            'stock' => $this->stock,
            'price' => $this->price,
            'price_final' => $this->price_final,
            'discount_type' => $this->discount_type,
            'discount_type_title' => $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount' => $this->discount,
            'is_default' => $this->isDefault(),
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
