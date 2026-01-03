<?php

namespace App\Http\Resources\Admin\CompanyAdmins;

use App\Http\Resources\Admin\Companies\CompaniesListResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $company_id
 * @property mixed $username
 * @property mixed $email
 * @property mixed $name
 * @property mixed $surname
 * @property mixed $is_root
 * @property mixed $password_must_change
 * @property mixed $image
 * @property mixed $company
 * @method isActive()
 */
class CompanyAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'surname' => $this->surname,
            'is_root' => $this->is_root,
            'is_active' => $this->isActive(),
            'password_must_change' => $this->password_must_change,
            'image' => $this->whenLoaded('media', function () {
                return $this->image;
            }),
            'company' => CompaniesListResource::make($this->whenLoaded('company')),
        ];
    }
}

