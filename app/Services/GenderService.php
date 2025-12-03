<?php

namespace App\Services;

use App\Enum\GenderEnum;
use App\Traits\AppServicesTrait;

class GenderService
{
    use AppServicesTrait;

    private static ?GenderService $instance = null;

    private function __construct() {

    }

    public static function instance(): GenderService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getItems(): array
    {
        return GenderEnum::getList();
    }
}
