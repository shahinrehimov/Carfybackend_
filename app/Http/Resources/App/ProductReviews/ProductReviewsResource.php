<?php

namespace App\Http\Resources\App\ProductReviews;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $rating
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $review
 * @property mixed $created_at
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
            'rating' => $this->rating,
            'name' => $this->name,
            'surname' => $this->surname,
            'review' => $this->review,
            'date' => $this->created_at?->format('d.m.Y'),
        ];
    }
}
