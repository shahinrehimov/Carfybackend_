<?php

namespace App\Rules;

use App\Services\LangService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class NestedDistinctRule implements ValidationRule
{
    /**
     * @param int $groupIndexPosition
     */
    public function __construct(private int $groupIndexPosition)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        static $grouped = [];

        $parts = explode('.', $attribute);

        if (!isset($parts[$this->groupIndexPosition])) {
            return;
        }

        $groupId = $parts[$this->groupIndexPosition];
        $groupKey = $this->groupIndexPosition . '_' . $groupId;

        if (!isset($grouped[$groupKey])) {
            $grouped[$groupKey] = [];
        }

        if (in_array($value, $grouped[$groupKey], true)) {
            $fail(
                LangService::instance()
                    ->setDefault('The @attribute field must be distinct within the same group.')
                    ->getLang('validation_distinct_within_group', [
                        '@attribute' => $attribute,
                    ])
            );
        } else {
            $grouped[$groupKey][] = $value;
        }
    }
}
