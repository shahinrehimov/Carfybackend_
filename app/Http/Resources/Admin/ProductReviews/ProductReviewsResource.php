<?php

namespace App\Http\Resources\Admin\ProductReviews;

use App\Http\Resources\Admin\Admins\AdminsListResource;
use App\Http\Resources\Admin\ProductVariants\ProductVariantsListResource;
use App\Http\Resources\Admin\Users\UsersListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $rating
 * @property mixed $full_name
 * @property mixed $review
 * @property mixed $created_at
 * @property mixed $approved_at
 * @method isApproved()
 */
class ProductReviewsResource extends JsonResource
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
            'product_variant' => ProductVariantsListResource::make($this->whenLoaded('productVariant')),
            'user' => UsersListResource::make($this->whenLoaded('user')),
            'rating' => $this->rating,
            'full_name' => $this->full_name,
            'review' => $this->review,
            'created_at' => $this->created_at?->toDateTimeString(),
            'is_approved' => $this->isApproved(),
            'approved_at' => $this->approved_at?->toDateTimeString(),
            'approved_user' => AdminsListResource::make($this->whenLoaded('approvedUser')),
        ];
    }
}
