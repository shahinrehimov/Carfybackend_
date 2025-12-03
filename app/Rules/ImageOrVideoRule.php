<?php

namespace App\Rules;

use App\Services\LangService;
use Illuminate\Contracts\Validation\Rule;

class ImageOrVideoRule implements Rule
{
    private string $attribute;

    public function passes($attribute, $value): bool
    {
        $this->attribute = $attribute;

        if (!is_file($value)) {
            return false;
        }

        $mime = $value->getMimeType();

        return str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
    }

    public function message(): string
    {
        return LangService::instance()
            ->setDefault('The @attribute field can only be an image or video.')
            ->getLang('field_can_only_image_or_video', [
                '@attribute' => $this->attribute,
            ]);
    }
}
