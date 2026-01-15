<?php

namespace App\Enum;

use App\Services\LangService;
use Illuminate\Support\Str;

enum ProductVariantApprovalStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function getList(): array
    {
        $array = [];

        foreach (self::cases() as $case) {
            $array[] = [
                'id' => $case,
                'title' => LangService::instance()
                    ->setDefault(Str::title($case->value))
                    ->getLang('product_variant_approval_status_' . $case->value),
            ];
        }

        return $array;
    }
}
