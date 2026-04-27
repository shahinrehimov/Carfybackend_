<?php

namespace App\Http\Requests\Sellers\Notifications;

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
            // Seller bulk always targets own customers (company_id injected server-side)
            // Seller individual: specific user_ids only
            'target_filters' => ['nullable', 'array'],
            'target_filters.user_ids' => ['nullable', 'array'],
            'target_filters.user_ids.*' => ['integer'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::enum(NotificationChannelEnum::class)],
            'action_type' => ['nullable', Rule::enum(NotificationActionTypeEnum::class)],
            'action_id' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'string', 'max:500'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.language_id' => ['required', 'integer', 'exists:languages,id'],
            'translations.*.title' => ['required', 'string', 'max:500'],
            'translations.*.body' => ['required', 'string'],
        ];
    }
}
