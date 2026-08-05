<?php

namespace App\Http\Resources\Admin\CommissionTiers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $min_amount
 * @property mixed $max_amount
 * @property mixed $percent
 */
class CommissionTierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Manats. min inclusive, max exclusive; null max = no upper bound
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'percent' => $this->percent,
        ];
    }
}
