<?php

namespace App\Http\Resources\App\UserCars;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'photo' => $this->photo,
            'brand' => $this->whenLoaded('brandModel', function () {
                return [
                    'id'    => $this->brandModel->brand?->id,
                    'title' => $this->brandModel->brand?->getLang('title'),
                ];
            }),
            'brand_model' => $this->whenLoaded('brandModel', function () {
                return [
                    'id'    => $this->brandModel->id,
                    'title' => $this->brandModel->getLang('title'),
                ];
            }),
            'car_type' => $this->whenLoaded('carType', function () {
                return [
                    'id'    => $this->carType->id,
                    'title' => $this->carType->getLang('title'),
                ];
            }),
            'engine_volume' => $this->whenLoaded('engineVolume', function () {
                return $this->engineVolume ? [
                    'id'           => $this->engineVolume->id,
                    'title'        => $this->engineVolume->getLang('title'),
                    'value_liters' => $this->engineVolume->value_liters,
                    'value_cc'     => $this->engineVolume->value_cc,
                ] : null;
            }),
            'fuel_type' => $this->whenLoaded('fuelType', function () {
                return $this->fuelType ? [
                    'id'    => $this->fuelType->id,
                    'title' => $this->fuelType->getLang('title'),
                ] : null;
            }),
            'transmission_type' => $this->whenLoaded('transmissionType', function () {
                return $this->transmissionType ? [
                    'id'    => $this->transmissionType->id,
                    'title' => $this->transmissionType->getLang('title'),
                ] : null;
            }),
            'car_color' => $this->whenLoaded('carColor', function () {
                return $this->carColor ? [
                    'id'       => $this->carColor->id,
                    'title'    => $this->carColor->getLang('title'),
                    'hex_code' => $this->carColor->hex_code,
                ] : null;
            }),
            'year'          => $this->year,
            'seat_count'    => $this->seat_count,
            'engine_number' => $this->engine_number,
            'vin_number'    => $this->vin_number,
            'created_at'    => $this->created_at?->toDateTimeString(),
        ];
    }
}
