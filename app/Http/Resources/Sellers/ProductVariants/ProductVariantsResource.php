<?php

namespace App\Http\Resources\Sellers\ProductVariants;

use App\Services\FitmentPresenter;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Sellers\Products\ProductsResource;
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
 * @property mixed $approval_status
 * @property mixed $approval_status_message
 * @property mixed $year
 * @property mixed $oem_code
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
            'title' => $this->title,
            'oem_code' => $this->oem_code,
            'already_attached' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->isNotEmpty(),
                $this->already_attached ?? false
            ),
            'seller_listing' => $this->whenLoaded('companyListings', function () {
                $cpv = $this->companyListings->first();
                if (!$cpv) return null;
                return [
                    'id'               => $cpv->id,
                    'uuid'             => $cpv->uuid,
                    'price'            => $cpv->price,
                    'price_final'      => $cpv->price_final,
                    'stock'            => $cpv->stock,
                    'discount_type'    => $cpv->discount_type,
                    'discount'         => $cpv->discount,
                    'discount_start_at'=> $cpv->discount_start_at?->toDateTimeString(),
                    'discount_end_at'  => $cpv->discount_end_at?->toDateTimeString(),
                    'city_id'          => $cpv->city_id,
                    'is_active'        => $cpv->is_active,
                    'approval_status'  => $cpv->approval_status,
                ];
            }),
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
            'stock' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->first()?->stock,
                $this->stock
            ),
            'price' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->first()?->price,
                $this->price
            ),
            'price_final' => $this->whenLoaded(
                'companyListings',
                fn() => $this->companyListings->first()?->price_final,
                $this->price_final
            ),
            'discount_type' => $this->discount_type,
            'discount_type_title' => $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount' => $this->discount,
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            // Deprecated: derived from fitments so existing clients keep working.
            'brand_models' => FitmentPresenter::instance()->brandModelsFor($this->resource),
            'fitments' => FitmentPresenter::instance()->forVariant($this->resource),
            'is_universal' => $this->is_universal,
            'year' => $this->year, // deprecated, use year_from / year_to
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
            'is_default' => $this->isDefault(),
            'is_active' => $this->isActive(),
            'approval_status' => $this->approval_status,
            'approval_status_label' => LangService::instance()
                ->setDefault(Str::title($this->approval_status))
                ->getLang('product_variant_approval_status_' . $this->approval_status),
            'approval_status_message' => $this->approval_status_message,
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
