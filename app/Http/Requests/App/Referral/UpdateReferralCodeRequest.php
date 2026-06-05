<?php

namespace App\Http\Requests\App\Referral;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settings  = SettingService::instance()->get();
        $minLength = (int) ($settings->referral_code_min_length ?? 4);
        $maxLength = (int) ($settings->referral_code_max_length ?? 20);

        return [
            'code' => [
                'required',
                'string',
                'min:' . $minLength,
                'max:' . $maxLength,
                'regex:/^[A-Za-z0-9]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Referral code may only contain letters and numbers.',
        ];
    }
}
