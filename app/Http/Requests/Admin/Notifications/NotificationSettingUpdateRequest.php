<?php

namespace App\Http\Requests\Admin\Notifications;

use App\Enum\NotificationChannelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationSettingUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*.id' => ['required', 'integer', 'exists:notification_type_settings,id'],
            'settings.*.is_active' => ['required', 'boolean'],
            'settings.*.channels' => ['required', 'array'],
            'settings.*.channels.*' => [Rule::enum(NotificationChannelEnum::class)],
        ];
    }
}
