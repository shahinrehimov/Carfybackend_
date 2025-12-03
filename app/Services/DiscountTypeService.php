<?php

namespace App\Services;

use App\Enum\DiscountTypeEnum;
use App\Traits\AppServicesTrait;

class DiscountTypeService
{
    use AppServicesTrait;

    private static ?DiscountTypeService $instance = null;

    private function __construct() {

    }

    public static function instance(): DiscountTypeService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): array
    {
        return DiscountTypeEnum::getList();
    }
}
