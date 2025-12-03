<?php

namespace App\Rules;

use App\Services\LangService;
use Illuminate\Contracts\Validation\ImplicitRule;

class FirstTranslationRequired implements ImplicitRule
{
    private string $attributeName;

    public function __construct(string $attributeName)
    {
        $this->attributeName = $attributeName;
    }

    public function passes($attribute, $value): bool
    {
        $parts = explode('.', $attribute);
        $count = count($parts);

        if ($count < 3) {
            return true;
        }

        $index = $parts[$count - 2];
        $field = $parts[$count - 1];

        if ($index === '0' && $field === $this->attributeName) {
            return !(blank($value) || !is_string($value));
        }

        return true;
    }

    public function message(): string
    {
        return LangService::instance()
            ->setDefault('The @attribute field is required for the first translation.')
            ->getLang('translation_first_language_field_is_required', [
                '@attribute' => $this->attributeName,
            ]);
    }
}
