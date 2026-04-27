<?php

namespace App\Http\Requests\Admin\Notifications;

use App\Enum\NotificationActionTypeEnum;
use App\Enum\NotificationChannelEnum;
use App\Enum\NotificationTargetTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target_type' => ['sometimes', Rule::enum(NotificationTargetTypeEnum::class)],
            'target_filters' => ['nullable', 'array'],
            'target_filters.user_ids' => ['nullable', 'array'],
            'target_filters.user_ids.*' => ['integer'],
            'target_filters.company_id' => ['nullable', 'integer'],
            'target_filters.recent_buyers_days' => ['nullable', 'integer', 'min:1'],
            'target_filters.cart_abandoners_days' => ['nullable', 'integer', 'min:1'],
            'target_filters.inactive_days' => ['nullable', 'integer', 'min:1'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => [Rule::enum(NotificationChannelEnum::class)],
            'action_type' => ['sometimes', 'nullable', Rule::enum(NotificationActionTypeEnum::class)],
            'action_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'translations' => ['sometimes', 'array', 'min:1'],
            'translations.*.language_id' => ['required_with:translations', 'integer', 'exists:languages,id'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:500'],
            'translations.*.body' => ['required_with:translations', 'string'],
        ];
    }
}
