<?php

namespace App\Http\Resources\Admin\Addresses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $address_line_1
 * @property mixed $id
 * @method isDefault()
 */
class AddressesListResource extends JsonResource
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
            'is_default' => $this->isDefault(),
            'address_line_1' => $this->address_line_1,
        ];
    }
}
