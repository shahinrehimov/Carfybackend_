<?php

namespace App\Http\Resources\App\Faqs;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FaqsListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'question' => $this->getLang('question'),
            'answer'   => $this->getLang('answer'),
        ];
    }
}
