<?php

namespace App\Http\Resources\Admin\CompanyAdmins;

use App\Http\Resources\Admin\Companies\CompaniesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $username
 * @property mixed $email
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $is_root
 * @property mixed $created_at
 * @property mixed $creatable
 * @property mixed $image
 * @property mixed $company
 * @method isActive()
 */
class CompanyAdminsResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'surname' => $this->surname,
            'full_name' => $this->name . ' ' . $this->surname,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'is_root' => $this->is_root,
            'is_active' => $this->isActive(),
            'company' => CompaniesListResource::make($this->whenLoaded('company')),
            'created_user' => $this->whenLoaded('creatable', function () {
                return $this->creatable?->username;
            }),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
