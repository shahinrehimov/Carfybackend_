<?php

namespace App\Http\Resources\Sellers\ProductVariants;

use App\Services\LangService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @property mixed $id
 * @property mixed $creatable
 * @property mixed $created_at
 * @property mixed $approval_status
 * @property mixed $approval_status_message
 */
class ProductVariantApprovalStatusHistoryResource extends JsonResource
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
