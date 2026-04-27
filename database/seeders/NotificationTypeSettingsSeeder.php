<?php

namespace Database\Seeders;

use App\Enum\NotificationEventTypeEnum;
use App\Models\NotificationTypeSetting;
use Illuminate\Database\Seeder;

class NotificationTypeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NotificationEventTypeEnum::cases() as $case) {
            NotificationTypeSetting::query()->updateOrCreate(
                ['event_type' => $case->value],
                [
                    'is_active' => true,
                    'channels' => NotificationEventTypeEnum::getDefaultChannels($case),
                ]
            );
        }

        $this->command->info('NotificationTypeSetting seeded for ' . count(NotificationEventTypeEnum::cases()) . ' event types.');
    }
}
