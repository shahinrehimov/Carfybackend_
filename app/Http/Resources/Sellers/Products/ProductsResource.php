<?php

namespace App\Http\Resources\Sellers\Products;

use App\Http\Resources\Admin\Brands\BrandsListResource;
use App\Http\Resources\Admin\Categories\CategoriesListResource;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsResource;
use App\Services\CompanyAdminService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
 */
class ProductsResource extends JsonResource
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
            'title' => $this->getLang('title'),
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'is_set' => $this->isSet(),
            'is_creator' => CompanyAdminService::instance()->checkUser()
                ? $this->company_id === CompanyAdminService::instance()->getUser()->company_id
                : null,
            'company' => $this->whenLoaded('company', function () {
                return $this->company ? [
                    'id' => $this->company->id,
                    'uuid' => $this->company->uuid,
                    'title' => $this->company->getLang('title'),
                ] : null;
            }),
            'sets_count' => $this->whenCounted('sets'),
            'brand' => BrandsListResource::make($this->whenLoaded('brand')),
            'main_category' => CategoriesListResource::make($this->whenLoaded('mainCategory')),
            'categories' => CategoriesListResource::collection($this->whenLoaded('categories')),
            'groups' => GroupsListResource::collection($this->whenLoaded('groups')),
            'variants_count' => $this->whenCounted('variants'),
            'pending_variants_count' => $this->whenCounted('pending_variants_count'),
            'pending_revisions_count' => $this->whenCounted('pending_revisions_count'),
            'pending_product_revisions_count' => $this->whenCounted('pending_product_revisions_count'),
            'variants' => ProductVariantsResource::collection($this->whenLoaded('variants')),
            'is_active' => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
