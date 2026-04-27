<?php

namespace App\Http\Requests\App\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class DeviceTokenRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:500'],
            'device_type' => ['required', 'string', 'in:ios,android,web'],
        ];
    }
}
