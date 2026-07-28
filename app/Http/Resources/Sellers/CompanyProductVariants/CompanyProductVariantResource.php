<?php

namespace App\Http\Resources\Sellers\CompanyProductVariants;

use App\Services\FitmentPresenter;
use App\Http\Resources\Admin\Cities\CitiesListResource;
use App\Http\Resources\Admin\Groups\GroupsListResource;
use App\Http\Resources\Sellers\Products\ProductsListResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantOptionsResource;
use App\Http\Resources\Sellers\ProductVariants\ProductVariantsListResource;
use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $uuid
 * @property mixed $stock
 * @property mixed $price
 * @property mixed $price_final
 * @property mixed $discount_type
 * @property mixed $discount
 * @property mixed $discount_start_at
 * @property mixed $discount_end_at
 * @property mixed $approval_status
 * @property mixed $approval_status_message
 * @property mixed $created_at
 * @method isActive()
 */
class CompanyProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'variant' => $this->whenLoaded('productVariant', function () {
                $variant = $this->productVariant;
                return [
                    'id' => $variant->id,
                    'uuid' => $variant->uuid,
                    'slug' => $variant->slug,
                    'oem_code' => $variant->oem_code,
                    'main_image' => $variant->relationLoaded('media') ? $variant->main_image : null,
                    'hover_image' => $variant->relationLoaded('media') ? $variant->hover_image : null,
                    'images' => $variant->relationLoaded('media') ? $variant->images : null,
                    'is_set' => $variant->isSet(),
                    'is_creator' => $variant->company_id === $this->company_id,
                    'sets' => $variant->relationLoaded('sets')
                        ? ProductVariantsListResource::collection($variant->sets)
                        : [],
                    'groups' => $variant->relationLoaded('groups')
                        ? GroupsListResource::collection($variant->groups)
                        : [],
                    'options' => $variant->relationLoaded('options')
                        ? ProductVariantOptionsResource::collection($variant->options)
                        : [],
                    // Deprecated: derived from fitments so existing clients keep working.
                    'brand_models' => FitmentPresenter::instance()->brandModelsFor($variant),
                    'fitments' => FitmentPresenter::instance()->forVariant($variant),
                    'is_universal' => $variant->is_universal,
                    'year' => $variant->year, // deprecated, use year_from / year_to
                    'year_from' => $variant->year_from,
                    'year_to' => $variant->year_to,
                    'product' => $variant->relationLoaded('product')
                        ? ProductsListResource::make($variant->product)
                        : null,
                ];
            }),
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
            'discount_start_at' => $this->discount_start_at?->toDateTimeString(),
            'discount_end_at' => $this->discount_end_at?->toDateTimeString(),
            'city' => CitiesListResource::make($this->whenLoaded('city')),
            'is_active' => $this->isActive(),
            'approval_status' => $this->approval_status,
            'approval_status_label' => LangService::instance()
                ->setDefault(Str::title($this->approval_status))
                ->getLang('product_variant_approval_status_' . $this->approval_status),
            'approval_status_message' => $this->approval_status_message,
            'seller_images' => $this->whenLoaded('media', function () {
                return $this->getMedia('company_product_variant_images')->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'url' => \App\Models\CompanyProductVariant::webUrlForMedia($media),
                    ];
                });
            }),
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'uuid' => $this->company->uuid,
                    'title' => $this->company->getLang('title'),
                ];
            }),
            'size_category' => $this->size_category,
            'weight_gram' => $this->weight_gram,
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
