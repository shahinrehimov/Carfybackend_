<?php

namespace App\Http\Resources\Sellers\ProductReviews;

use App\Http\Resources\Sellers\ProductVariants\ProductVariantsListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $rating
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $review
 * @property mixed $created_at
 * @property mixed $approved_at
 * @method isApproved()
 */
class ProductReviewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'product_variant' => ProductVariantsListResource::make($this->whenLoaded('productVariant')),
            'rating'         => $this->rating,
            'name'           => $this->name,
            'surname'        => $this->surname,
            'review'         => $this->review,
            'created_at'     => $this->created_at?->toDateTimeString(),
            'is_approved'    => $this->isApproved(),
            'approved_at'    => $this->approved_at?->toDateTimeString(),
            'images'         => $this->whenLoaded('media', function () {
                return $this->images;
            }),
        ];
    }
}
