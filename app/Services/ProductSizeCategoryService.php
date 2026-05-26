<?php

namespace App\Services;

use App\Enum\ProductSizeCategoryEnum;
use App\Traits\AppServicesTrait;

class ProductSizeCategoryService
{
    use AppServicesTrait;

    private static ?ProductSizeCategoryService $instance = null;

    private function __construct() {

    }

    public static function instance(): ProductSizeCategoryService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): array
    {
        return ProductSizeCategoryEnum::getList();
    }
}
