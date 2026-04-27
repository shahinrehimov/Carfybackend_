<?php

namespace App\Http\Requests\Admin\Notifications;

use App\Enum\NotificationActionTypeEnum;
use App\Enum\NotificationChannelEnum;
use App\Enum\NotificationTargetTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target_type' => ['required', Rule::enum(NotificationTargetTypeEnum::class)],
            'target_filters' => ['nullable', 'array'],
            'target_filters.user_ids' => ['nullable', 'array'],
            'target_filters.user_ids.*' => ['integer'],
            'target_filters.company_id' => ['nullable', 'integer'],
            'target_filters.recent_buyers_days' => ['nullable', 'integer', 'min:1'],
            'target_filters.cart_abandoners_days' => ['nullable', 'integer', 'min:1'],
            'target_filters.inactive_days' => ['nullable', 'integer', 'min:1'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::enum(NotificationChannelEnum::class)],
            'action_type' => ['nullable', Rule::enum(NotificationActionTypeEnum::class)],
            'action_id' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:500'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language_id' => ['required', 'integer', 'exists:languages,id'],
            'translations.*.title' => ['required', 'string', 'max:500'],
            'translations.*.body' => ['required', 'string'],
        ];
    }
}
