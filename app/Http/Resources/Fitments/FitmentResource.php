<?php

namespace App\Http\Resources\Fitments;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One fitment rule. A null brand means the listing fits every car, a null model
 * means every model of that brand, and a null year bound is open-ended.
 *
 * @property mixed $brand
 * @property mixed $brand_model
 * @property mixed $brandModel
 * @property mixed $year_from
 * @property mixed $year_to
 */
class FitmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'brand' => $this->brand
                ? ['id' => $this->brand->id, 'title' => $this->brand->getLang('title')]
                : null,
            'brand_model' => $this->brandModel
                ? ['id' => $this->brandModel->id, 'title' => $this->brandModel->getLang('title')]
                : null,
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
        ];
    }
}
