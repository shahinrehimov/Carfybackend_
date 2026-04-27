<?php

namespace App\Http\Requests\Sellers\Notifications;

use App\Enum\NotificationActionTypeEnum;
use App\Enum\NotificationChannelEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => [Rule::enum(NotificationChannelEnum::class)],
            'action_type' => ['sometimes', 'nullable', Rule::enum(NotificationActionTypeEnum::class)],
            'action_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image' => ['sometimes', 'nullable', 'string', 'max:500'],
            'translations' => ['sometimes', 'array', 'min:1'],
            'translations.*.language_id' => ['required_with:translations', 'integer', 'exists:languages,id'],
            'translations.*.title' => ['required_with:translations', 'string', 'max:500'],
            'translations.*.body' => ['required_with:translations', 'string'],
        ];
    }
}
