<?php

namespace App\Http\Requests\Admin\Settings;

use App\Enum\SettingTypeEnum;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * @property mixed $type
 */
class SettingUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $setting = Setting::query()->where('key', $this->route('key'))->firstOrFail();

        return [
            'value' => [
                'filled',
                Rule::requiredIf(fn() => $setting->type === SettingTypeEnum::STRING->value),
                Rule::excludeIf(fn() => $setting->type !== SettingTypeEnum::STRING->value),
                'string',
                'max:1000',
            ],
            'file' => [
                'filled',
                Rule::requiredIf(fn() => $setting->type === SettingTypeEnum::FILE->value),
                Rule::excludeIf(fn() => $setting->type !== SettingTypeEnum::FILE->value),
                File::types(['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'mp4', 'avi', 'mov', 'mkv', 'webm'])
                    ->max(100 * 1024),
            ],
        ];
    }
}
