<?php

namespace App\Http\Resources\Admin\Faqs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'question'     => $this->getLang('question'),
            'answer'       => $this->getLang('answer'),
            'sort'         => $this->sort,
            'is_active'    => $this->isActive(),
            'created_user' => $this->whenLoaded('creatable', fn() => $this->creatable?->username),
            'created_date' => $this->created_at?->toDateTimeString(),
        ];
    }
}
