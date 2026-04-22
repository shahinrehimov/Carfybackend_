<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class OrderAutoCanselMinutesSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(
            ['key' => 'order_auto_cancel_minutes'],
            [
                'type' => 'string',
                'value' => '60',
            ]
        );
    }
}
