<?php

namespace App\Http\Resources\App\ProductVariants;

use App\Services\FitmentPresenter;
use App\Services\FitmentSelectionService;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\App\CompanyProductVariants\CompanyProductVariantListResource;
use App\Http\Resources\App\Products\ProductResource;
use App\Http\Resources\Sellers\StoreProfile\StoreProfileWorkingHourResource;
use App\Models\CompanyProductVariant;
use App\Services\LangService;
use App\Services\StoreAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $slug
 * @property mixed $hover_image
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @property mixed $discount_type
 * @property mixed $discount
 * @property mixed $mainCategory
 * @property mixed $main_image
 * @property mixed $images
 * @property mixed $related_products
 * @property mixed $in_wishlist
 * @property mixed $in_basket
 * @property mixed $ratings
 * @property mixed $title
 * @property mixed $year
 * @property mixed $oem_code
 * @method getLang(string $string)
 * @method isSet()
 */
class ProductVariantResource extends JsonResource
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
            'oem_code' => $this->oem_code,
            'title' => $this->title,
            'main_image' => $this->whenLoaded('media', function () {
                return $this->main_image;
            }),
            'hover_image' => $this->whenLoaded('media', function () {
                return $this->hover_image;
            }),
            'images' => $this->whenLoaded('media', function () {
                return $this->images['urls'] ?? [];
            }),
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            // Deprecated: derived from fitments so existing clients keep working.
            'brand_models' => FitmentPresenter::instance()->brandModelsFor($this->resource),
            'fitments' => FitmentPresenter::instance()->forVariant($this->resource),
            'is_universal' => $this->is_universal,
            // When true the client must ask which car the part is for before
            // adding it to the basket; the options drive that modal.
            'requires_fitment_selection' => FitmentSelectionService::instance()->requires($this->resource),
            'fitment_selection_options' => FitmentSelectionService::instance()->optionsFor($this->resource),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'is_set' => $this->isSet(),
            'sets' => ProductVariantsResource::collection($this->whenLoaded('sets')),
            'stock' => $this->stock,
            'price' => $this->price,
            'price_final' => $this->price_final,
            'has_discount' => $this->price != $this->price_final,
            'discount_type' => $this->discount_type,
            'discount_type_title' => $this->discount_type
                ? LangService::instance()
                    ->setDefault(Str::title($this->discount_type))
                    ->getLang('discount_types_' . $this->discount_type)
                : null,
            'discount' => $this->discount,
            'year' => $this->year, // deprecated, use year_from / year_to
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
            'in_wishlist' => $this->in_wishlist ?? false,
            'in_basket' => $this->in_basket ?? false,
            'options' => ProductVariantOptionsResource::collection($this->whenLoaded('options')),
            'ratings' => $this->whenNotNull($this->ratings),
            'related_products' => CompanyProductVariantListResource::collection($this->whenNotNull($this->related_products)),

            // Seller data (price/stock/discount already overridden at top level via setRawAttributes)
            'seller' => $this->when(isset($this->seller_listing), function () {
                if (!$this->seller_listing) return null;
                $sl = $this->seller_listing;
                return [
                    'uuid'         => $sl->uuid,
                    'store_code'   => $sl->company?->store_code,
                    'label'        => $sl->company?->getSellerLabel(),
                    'seller_label' => $sl->company?->getSellerLabel(),
                    // Whether the store takes orders right now; `message`
                    // explains when it reopens and is null while open.
                    'availability' => StoreAvailabilityService::instance()->for($sl->company),
                    // Full weekly schedule, product detail only — list rows get
                    // `availability` alone to keep the payload small.
                    'working_hours' => $sl->company?->relationLoaded('workingHours')
                        ? StoreProfileWorkingHourResource::collection(
                            $sl->company->workingHours->sortBy('day_of_week')->values()
                        )
                        : [],
                    'city'         => $sl->relationLoaded('city')
                        ? CitiesListResource::make($sl->city)
                        : null,
                    'images'       => $sl->relationLoaded('media')
                        ? $sl->getMedia('company_product_variant_images')->map(fn($m) => ['url' => CompanyProductVariant::webUrlForMedia($m)])
                        : [],
                    'gallery'      => $sl->listing_gallery,
                ];
            }),
            'sellers_count' => $this->whenNotNull($this->sellers_count),

            // Other sellers, in the canonical product list structure
            'other_sellers' => CompanyProductVariantListResource::collection(
                $this->whenNotNull($this->other_sellers)
            ),
            'other_sellers_total' => $this->whenNotNull($this->other_sellers_total),
        ];
    }
}
