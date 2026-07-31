<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\CommissionService;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Platform-wide commission rate, used whenever neither the seller nor the
 * product category defines its own.
 */
class DefaultCommissionPercentSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => CommissionService::DEFAULT_PERCENT_SETTING],
            [
                'type'  => 'string',
                'value' => '10',
            ]
        );

        SettingService::instance()->setCache();
    }
}
